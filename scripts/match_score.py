import json
import re
import sys
from typing import Any

from ollama import chat


MODEL_NAME = "gemma2:2b"


def clean_json_block(content: str) -> str:
    text = content.strip()

    if text.startswith("```json"):
        text = text[7:]
    elif text.startswith("```"):
        text = text[3:]

    if text.endswith("```"):
        text = text[:-3]

    text = text.strip()
    match = re.search(r"\{.*\}", text, re.DOTALL)

    return match.group(0).strip() if match else text


def call_model(prompt: str) -> dict[str, Any]:
    response = chat(model=MODEL_NAME, messages=[{"role": "user", "content": prompt}])
    content = response["message"]["content"]
    cleaned = clean_json_block(content)

    return json.loads(cleaned)


def build_prompt(payload: dict[str, Any]) -> str:
    candidate = payload.get("candidate", {})
    offer = payload.get("offer", {})

    return f"""
Tu es un moteur IA de matching entre un candidat et une offre d'emploi.

Ta mission:
- comprendre l'ensemble du profil candidat
- comprendre l'ensemble de l'offre, y compris le titre, les competences et la description
- comparer intelligemment les deux
- produire des donnees structurees utilisables pour calculer un score

Dossier candidat:
- Headline: {candidate.get("headline", "Non renseigne")}
- Hard skills: {candidate.get("hardSkills", "Non renseigne")}
- Soft skills: {candidate.get("softSkills", "Non renseigne")}
- Bio: {candidate.get("bio", "Non renseigne")}
- Localisation: {candidate.get("location", "Non renseigne")}
- Niveau: {candidate.get("niveau", "Non renseigne")}
- Diplome: {candidate.get("degree", "Non renseigne")}
- Domaine d'etude: {candidate.get("fieldOfStudy", "Non renseigne")}

Offre:
- Titre: {offer.get("titre", "Non renseigne")}
- Competences requises: {offer.get("competences", "Non renseigne")}
- Description: {offer.get("description", "Non renseigne")}
- Type de contrat: {offer.get("typeContrat", "Non renseigne")}
- Localisation: {offer.get("localisation", "Non renseigne")}
- Qualification: {offer.get("qualification", "Non renseigne")}
- Experience requise: {offer.get("experience", "Non renseigne")}

Contraintes:
- reponds uniquement avec du JSON brut
- pas de markdown
- score_global doit etre un entier de 0 a 100
- strengths: 2 a 4 points maximum
- missing: 0 a 4 points maximum
- summary: 1 phrase courte
- utilise uniquement les informations presentes
- ne traite pas les mots generiques comme des competences
- comprends le sens global de la description, pas uniquement des mots isoles

Format exact:
{{
  "score_global": 74,
  "summary": "Phrase courte en francais",
  "strengths": ["point 1", "point 2"],
  "missing": ["element 1"],
  "focus": {{
    "role_fit": 0,
    "skills_fit": 0,
    "description_fit": 0,
    "profile_fit": 0
  }}
}}
"""


def build_fallback(payload: dict[str, Any]) -> dict[str, Any]:
    candidate = payload.get("candidate", {})
    offer = payload.get("offer", {})

    candidate_text = " ".join(
        str(candidate.get(key, "") or "")
        for key in ("headline", "hardSkills", "softSkills", "bio", "fieldOfStudy", "degree")
    ).lower()
    offer_text = " ".join(
        str(offer.get(key, "") or "")
        for key in ("titre", "competences", "description", "qualification", "experience")
    ).lower()

    simple_tokens = {
        token for token in re.split(r"[^a-z0-9+#]+", candidate_text)
        if len(token) >= 3
    }
    overlap = [
        token for token in re.split(r"[^a-z0-9+#]+", offer_text)
        if len(token) >= 3 and token in simple_tokens
    ]
    unique_overlap = []
    for token in overlap:
        if token not in unique_overlap:
            unique_overlap.append(token)

    overlap_count = len(unique_overlap[:4])
    score = min(85, 45 + (overlap_count * 10))

    return {
        "score_global": score,
        "summary": "Compatibilite analysee automatiquement a partir du profil et de l'offre.",
        "strengths": unique_overlap[:3],
        "missing": [],
        "focus": {
            "role_fit": score,
            "skills_fit": score,
            "description_fit": max(45, score - 5),
            "profile_fit": score,
        },
    }


def main() -> None:
    raw_payload = sys.argv[1] if len(sys.argv) > 1 else "{}"

    try:
        payload = json.loads(raw_payload)
    except json.JSONDecodeError:
        payload = {}

    try:
        result = call_model(build_prompt(payload))
    except Exception:
        result = build_fallback(payload)

    print(json.dumps(result, ensure_ascii=True))


if __name__ == "__main__":
    main()