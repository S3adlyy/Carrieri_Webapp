#!/usr/bin/env python3
"""Generate recommendation reasons using Ollama AI — BATCH mode.

Sends ALL courses in ONE prompt to Mistral/Ollama.
One API call for all recommendations = fast even with large models.

Input JSON (batch):
{
  "candidate_level": "Débutant",
  "candidate_skills": ["html", "css"],
  "courses": [
    {
      "course_title": "Symfony 6.4",
      "course_skills": ["php", "symfony"],
      "course_level": "Intermédiaire",
      "course_duration": 12,
      "skill_matches": {"exact": 1, "partial": 2}
    },
    ...
  ]
}

Output JSON:
{
  "results": [
    {"course_title": "Symfony 6.4", "reasons": ["...", "...", "..."]},
    ...
  ],
  "source": "ollama"
}
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
import urllib.request
from typing import Any, Dict, List, Tuple

DEFAULT_MODEL   = os.getenv("OLLAMA_MODEL",   "llama3.2:3b")
DEFAULT_HOST    = os.getenv("OLLAMA_HOST",    "http://127.0.0.1:11434")
TIMEOUT_SECONDS = int(os.getenv("OLLAMA_TIMEOUT", "120"))


# ---------------------------------------------------------------------------
# Level helpers
# ---------------------------------------------------------------------------

def _normalize_level(value: str) -> str:
    v = (value or "").strip().lower()
    v = (
        v.replace("é","e").replace("è","e").replace("ê","e").replace("ë","e")
        .replace("à","a").replace("â","a").replace("î","i").replace("ï","i")
        .replace("ô","o").replace("ù","u").replace("û","u").replace("ç","c")
    )
    if "debut"  in v: return "Débutant"
    if "inter"  in v: return "Intermédiaire"
    if "avan"   in v: return "Avancé"
    if "expert" in v: return "Expert"
    return "Débutant"


def _next_level(current: str) -> str:
    order = ["Débutant", "Intermédiaire", "Avancé", "Expert"]
    try:
        return order[order.index(current) + 1]
    except (ValueError, IndexError):
        return current


# ---------------------------------------------------------------------------
# Grounded reasons (accurate fallback, always French)
# ---------------------------------------------------------------------------

def _grounded_reasons(candidate_level: str, candidate_skills: List[str], course: Dict[str, Any]) -> List[str]:
    course_level    = _normalize_level(str(course.get("course_level", "")))
    course_duration = int(course.get("course_duration", 0) or 0)
    course_skills   = [s.strip() for s in (course.get("course_skills") or []) if s.strip()]
    matches         = course.get("skill_matches") or {}
    exact           = int(matches.get("exact", 0)   or 0)
    partial         = int(matches.get("partial", 0) or 0)

    skill1 = course_skills[0].lower()    if course_skills    else "nouvelles compétences"
    skill2 = course_skills[1].lower()    if len(course_skills) > 1 else skill1
    cand1  = candidate_skills[0].lower() if candidate_skills else "vos compétences"

    if exact > 0:
        r1 = f"Renforce vos compétences en {cand1}, directement applicables."
    elif partial > 0:
        r1 = f"Approfondit {skill1}, proche de votre expertise en {cand1}."
    else:
        r1 = f"Vous initie à {skill1} et {skill2}, compétences clés du marché."

    if course_level == candidate_level:
        r2 = f"Parfaitement adapté à votre niveau {candidate_level}."
    elif course_level == _next_level(candidate_level):
        r2 = f"Idéal pour progresser de {candidate_level} vers {course_level}."
    else:
        r2 = f"Enrichit votre profil avec des outils de niveau {course_level}."

    if 0 < course_duration <= 5:
        r3 = f"Format ultra-court ({course_duration}h) : résultats immédiats."
    elif 0 < course_duration <= 12:
        r3 = f"En {course_duration}h, maîtrisez {skill1} rapidement."
    elif course_duration > 12:
        r3 = f"Formation complète de {course_duration}h pour maîtriser {skill1}."
    else:
        r3 = f"Compétences en {skill1} très recherchées sur le marché."

    return [r1, r2, r3]


# ---------------------------------------------------------------------------
# Build batch prompt — ONE call for all courses
# ---------------------------------------------------------------------------

def _build_batch_prompt(candidate_level: str, candidate_skills: List[str], courses: List[Dict[str, Any]]) -> str:
    cand_skills_str = ", ".join(candidate_skills) if candidate_skills else "non précisées"

    lines = [
        "Tu es un conseiller en formation professionnelle.",
        f"Candidat : niveau {candidate_level}, compétences acquises : {cand_skills_str}.",
        "",
        "Pour chaque cours numéroté, génère exactement 3 raisons COURTES (max 12 mots chacune) "
        "en français pourquoi ce cours est recommandé à ce candidat.",
        "Réponds UNIQUEMENT avec le format suivant, sans rien d'autre :",
        "",
    ]

    for i, course in enumerate(courses, 1):
        title    = str(course.get("course_title", f"Cours {i}"))
        skills   = ", ".join(str(s) for s in (course.get("course_skills") or []))
        level    = _normalize_level(str(course.get("course_level", "")))
        duration = int(course.get("course_duration", 0) or 0)
        matches  = course.get("skill_matches") or {}
        exact    = int(matches.get("exact", 0)   or 0)
        partial  = int(matches.get("partial", 0) or 0)

        dur_str  = f"{duration}h" if duration else "?"
        match_str = f"{exact} exacte(s), {partial} partielle(s)" if (exact or partial) else "aucune"

        lines.append(f"COURS {i} : \"{title}\" | compétences : {skills} | niveau : {level} | durée : {dur_str} | correspondances : {match_str}")
        lines.append(f"COURS {i} :")
        lines.append("1.")
        lines.append("2.")
        lines.append("3.")
        lines.append("")

    return "\n".join(lines)


# ---------------------------------------------------------------------------
# Call Ollama
# ---------------------------------------------------------------------------

def _call_ollama(prompt: str) -> str:
    # Force CPU-only for large models (mistral, llama3, etc.) to avoid VRAM OOM.
    # tinyllama (637MB) fits in GPU VRAM; mistral 7B (4.4GB) does not.
    is_large_model = any(m in DEFAULT_MODEL.lower() for m in ("mistral", "llama3", "gemma", "phi3", "deepseek"))
    options: Dict[str, Any] = {
        "temperature": 0.3,
        "num_predict": 600,
        "stop": ["---", "Note:", "Remarque:"],
    }
    if is_large_model:
        options["num_gpu"] = 0   # CPU-only: avoids VRAM OOM on Laptop GPUs

    url  = f"{DEFAULT_HOST.rstrip('/')}/api/generate"
    body = json.dumps({
        "model":  DEFAULT_MODEL,
        "prompt": prompt,
        "stream": False,
        "options": options,
    }).encode("utf-8")

    req = urllib.request.Request(
        url, data=body,
        headers={"Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=TIMEOUT_SECONDS) as resp:
        data = json.loads(resp.read().decode("utf-8"))
        return data.get("response", "").strip()


# ---------------------------------------------------------------------------
# Parse batch response — extract per-course reasons
# ---------------------------------------------------------------------------

def _parse_batch_response(raw: str, n_courses: int) -> List[List[str]]:
    """
    Parse the model output to extract 3 reasons per course.
    Looks for patterns like:
      COURS 1 :
      1. ...
      2. ...
      3. ...
    """
    results: List[List[str]] = []

    # Split by COURS N: markers
    # Match "COURS 1 :", "COURS 1:", "COURS1:", etc.
    sections = re.split(r'COURS\s*\d+\s*:', raw, flags=re.IGNORECASE)

    # sections[0] is text before first COURS, sections[1..n] are the course blocks
    course_blocks = sections[1:] if len(sections) > 1 else []

    for block in course_blocks:
        reasons: List[str] = []
        # Extract numbered lines
        numbered = re.findall(r'^\s*\d+[\.\)]\s*(.+)$', block, re.MULTILINE)
        for line in numbered:
            line = line.strip().strip('"').strip("'")
            if len(line) > 5:
                if not line[-1] in ".!?":
                    line += "."
                reasons.append(line)
            if len(reasons) >= 3:
                break
        results.append(reasons[:3])

    return results


# ---------------------------------------------------------------------------
# Main pipeline
# ---------------------------------------------------------------------------

def _run(payload: Dict[str, Any], require_ollama: bool) -> Dict[str, Any]:
    candidate_level  = _normalize_level(str(payload.get("candidate_level", "")))
    candidate_skills = [s.strip() for s in (payload.get("candidate_skills") or []) if s.strip()]
    courses          = payload.get("courses") or []

    if not courses:
        return {"results": [], "source": "ollama"}

    # Build grounded reasons for all courses (always accurate fallback)
    all_grounded = [
        _grounded_reasons(candidate_level, candidate_skills, c)
        for c in courses
    ]

    sys.stderr.write(f"[ollama] Batch: {len(courses)} course(s) | model: {DEFAULT_MODEL} @ {DEFAULT_HOST}\n")

    try:
        prompt = _build_batch_prompt(candidate_level, candidate_skills, courses)
        sys.stderr.write(f"[ollama] Sending batch prompt ({len(prompt)} chars)…\n")

        raw = _call_ollama(prompt)
        sys.stderr.write(f"[ollama] Raw response ({len(raw)} chars): {raw[:400]!r}\n")

        parsed = _parse_batch_response(raw, len(courses))
        sys.stderr.write(f"[ollama] Parsed {len(parsed)} course block(s)\n")

        results = []
        for i, course in enumerate(courses):
            title         = str(course.get("course_title", f"Cours {i+1}"))
            ai_reasons    = parsed[i] if i < len(parsed) else []
            grounded      = all_grounded[i]

            # Fill missing slots with grounded reasons
            final_reasons = list(ai_reasons)
            for g in grounded:
                if len(final_reasons) >= 3:
                    break
                if g not in final_reasons:
                    final_reasons.append(g)

            ai_count = len(ai_reasons)
            sys.stderr.write(f"[ollama] Course {i+1}: {ai_count} AI reason(s), {3-ai_count} grounded\n")
            results.append({"course_title": title, "reasons": final_reasons[:3]})

        return {"results": results, "source": "ollama"}

    except Exception as exc:
        sys.stderr.write(f"[ollama] EXCEPTION: {exc}\n")
        if require_ollama:
            raise RuntimeError(f"Ollama unavailable: {exc}") from exc

    sys.stderr.write("[ollama] Ollama down — using grounded fallback for all courses\n")
    return {
        "results": [
            {"course_title": str(c.get("course_title", "")), "reasons": g}
            for c, g in zip(courses, all_grounded)
        ],
        "source": "fallback",
    }


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def _read_json_input(arg_value: str) -> Dict[str, Any]:
    if arg_value == "-":
        raw = sys.stdin.read()
    elif os.path.isfile(arg_value):
        with open(arg_value, "r", encoding="utf-8") as f:
            raw = f.read()
    else:
        raw = arg_value
    raw = raw.strip()
    if not raw:
        raise ValueError("Empty JSON input")
    data = json.loads(raw)
    if not isinstance(data, dict):
        raise ValueError("Input JSON must be an object")
    return data


def main() -> int:
    parser = argparse.ArgumentParser(description="Ollama batch recommendation reasons")
    parser.add_argument("json_input", nargs="?", default="-")
    parser.add_argument("--require-ollama", action="store_true")
    args = parser.parse_args()
    try:
        payload = _read_json_input(args.json_input)
        result  = _run(payload, args.require_ollama)
        print(json.dumps(result, ensure_ascii=False))
        return 0
    except Exception as exc:
        sys.stderr.write(f"Error: {exc}\n")
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
