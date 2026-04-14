"""
AI Code Evaluation API
======================
FastAPI service that evaluates code submissions using AI (Ollama - 100% gratuit).
"""
from fastapi import FastAPI, HTTPException, Request  # Add Request here
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from typing import Optional
import json
import re
import logging
import requests
from datetime import datetime
from datetime import datetime

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="Carrieri – AI Code Evaluator",
    description="Evaluates candidate code submissions using Ollama (gratuit)",
    version="1.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["POST", "GET", "OPTIONS"],
    allow_headers=["*"],
)

# Configuration Ollama
OLLAMA_URL = "http://localhost:11434"
MODEL_NAME = "starcoder2:3b"  # Votre modèle installé

class EvaluationRequest(BaseModel):
    code: str
    language: str = "javascript"
    mission_description: str
    mission_title: Optional[str] = None
    score_min: Optional[int] = 60

class TestResult(BaseModel):
    test_name: str
    passed: bool
    description: str
    details: Optional[str] = None

class EvaluationResponse(BaseModel):
    score: float
    statut: str
    feedback: str
    resultat_html: str
    test_results: list[TestResult]
    language_detected: str
    summary: str

def _build_prompt(req: EvaluationRequest) -> str:
    """Construit le prompt pour Ollama"""
    return f"""Tu es un expert en évaluation de code. Évalue le code suivant.

Mission: {req.mission_title or 'Sans titre'}
Description: {req.mission_description}
Langage: {req.language}

Code à évaluer:
Barème (100 points):
- Correction: 40 points
- Qualité du code: 20 points
- Gestion des erreurs: 15 points
- Efficacité: 15 points
- Nommage: 10 points

Réponds UNIQUEMENT avec ce JSON:
{{"score": 75, "feedback": "feedback en français", "summary": "résumé", "language_detected": "{req.language}"}}"""

def _evaluate_fallback(req: EvaluationRequest) -> dict:
    """Évaluation simple quand Ollama échoue"""
    code = req.code.lower()
    code_len = len(req.code.strip())
    code_lines = req.code.strip().split('\n')

    # Calcul du score
    score = 30  # Score de base

    # Présence de fonctions (20 points)
    if 'def ' in code or 'function' in code:
        score += 20
    elif 'class ' in code:
        score += 15
    else:
        score += 5

    # Gestion d'erreurs (15 points)
    if 'try' in code or 'except' in code or 'catch' in code:
        score += 15
    elif 'if' in code and ('error' in code or 'invalid' in code):
        score += 10

    # Commentaires (10 points)
    if '#' in code or '//' in code or '"""' in code:
        score += 10

    # Structure (15 points)
    if code_len > 100:
        score += 10
    if len(code_lines) > 5:
        score += 5

    # Retour et logique (20 points)
    if 'return' in code:
        score += 10
    if 'if' in code or 'for' in code or 'while' in code:
        score += 10

    # Nommage (10 points)
    if re.search(r'[a-z]+_[a-z]+', code) or re.search(r'[a-z]+[A-Z]', code):
        score += 10

    score = min(100, max(0, score))

    # Résultats des tests
    test_results = [
        {
            "test_name": "Fonctions/Modularité",
            "passed": 'def ' in code or 'function' in code,
            "description": "Le code utilise des fonctions",
            "details": "Utilisez des fonctions" if not ('def ' in code or 'function' in code) else "✓ Bon"
        },
        {
            "test_name": "Gestion des erreurs",
            "passed": 'try' in code or 'except' in code or 'catch' in code,
            "description": "Gestion des erreurs",
            "details": "Ajoutez try/except" if not ('try' in code or 'except' in code) else "✓ OK"
        },
        {
            "test_name": "Documentation",
            "passed": '#' in code or '//' in code or '"""' in code,
            "description": "Code commenté",
            "details": "Ajoutez des commentaires" if not ('#' in code or '//' in code) else "✓ OK"
        },
        {
            "test_name": "Structure",
            "passed": code_len > 50,
            "description": "Structure du code",
            "details": f"{code_len} caractères"
        },
        {
            "test_name": "Nommage",
            "passed": re.search(r'[a-z]+_[a-z]+', code) or re.search(r'[a-z]+[A-Z]', code),
            "description": "Conventions de nommage",
            "details": "Utilisez snake_case/camelCase" if not (re.search(r'[a-z]+_[a-z]+', code) or re.search(r'[a-z]+[A-Z]', code)) else "✓ OK"
        }
    ]

    # Feedback
    if score >= 80:
        feedback = "Excellent travail ! Code bien structuré, documenté et suit les bonnes pratiques."
    elif score >= 60:
        feedback = f"Bon travail ! Score: {score}/100. Ajoutez des commentaires et gérez mieux les erreurs."
    elif score >= 40:
        feedback = f"Travail acceptable. Score: {score}/100. Améliorez la structure et la documentation."
    else:
        feedback = f"Code à améliorer. Score: {score}/100. Révisez la structure et ajoutez des fonctions."

    summary = f"Score: {score}/100 - {'Accepté' if score >= 60 else 'Refusé'}"

    return {
        "score": score,
        "feedback": feedback,
        "test_results": test_results,
        "summary": summary,
        "language_detected": req.language
    }

def _build_html(test_results: list, score: float, statut: str) -> str:
    """Génère le HTML des résultats"""
    color = "#28a745" if statut == "accepte" else "#dc3545"
    rows = ""
    passed_count = 0

    for t in test_results:
        passed = t.get("passed", False)
        if passed:
            passed_count += 1
        icon = "✓" if passed else "✗"
        row_color = "#d4edda" if passed else "#f8d7da"
        rows += f"""
        <tr style="background:{row_color}">
            <td style="padding:10px;border:1px solid #ddd;">{icon} <strong>{t.get('test_name', '')}</strong></td>
            <td style="padding:10px;border:1px solid #ddd;">{t.get('description', '')}</td>
            <td style="padding:10px;border:1px solid #ddd;">{t.get('details', '')}</td>
        </tr>"""

    total = len(test_results)

    return f"""
<div class="ai-evaluation" style="font-family:Arial,sans-serif;max-width:100%;">
    <div style="display:flex;align-items:center;gap:15px;margin-bottom:15px;padding:10px;background:#f8f9fa;border-radius:8px;">
        <div style="font-size:2.5rem;font-weight:bold;color:{color}">{score:.0f}<span style="font-size:1rem">%</span></div>
        <div>
            <span style="background:{color};color:#fff;padding:5px 15px;border-radius:20px;font-size:.9rem;font-weight:bold;">
                {"✓ ACCEPTÉ" if statut == "accepte" else "✗ REFUSÉ"}
            </span>
            <div style="font-size:.85rem;color:#666;margin-top:5px;">{passed_count}/{total} critères validés</div>
        </div>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-top:10px;">
        <thead>
            <tr style="background:#343a40;color:#fff;">
                <th style="padding:10px;text-align:left;border:1px solid #455;">Critère</th>
                <th style="padding:10px;text-align:left;border:1px solid #455;">Description</th>
                <th style="padding:10px;text-align:left;border:1px solid #455;">Détails</th>
            </tr>
        </thead>
        <tbody>{rows}</tbody>
    </table>
    <p style="font-size:.7rem;color:#888;margin-top:10px;text-align:center;">⚡ Évalué par IA – Carrieri Platform</p>
</div>
"""

@app.get("/")
def root():
    return {
        "message": "AI Code Evaluator API",
        "status": "running",
        "model": MODEL_NAME,
        "endpoints": {
            "GET /health": "Health check",
            "POST /evaluate": "Evaluate code submission"
        }
    }

@app.get("/health")
def health():
    # Vérifier Ollama
    ollama_status = "disconnected"
    try:
        r = requests.get(f"{OLLAMA_URL}/api/tags", timeout=2)
        if r.status_code == 200:
            ollama_status = "connected"
    except:
        pass

    return {
        "status": "ok",
        "service": "ai-code-evaluator",
        "ollama": ollama_status,
        "model": MODEL_NAME
    }


@app.get("/test-analyze")
def test_analyze():
    """Test endpoint for mission analysis"""
    test_description = "sum of 2 values with python"

    result = {
        "examples": [
            {
                "input": "nums = [2, 7, 11, 15], target = 9",
                "output": "[0, 1]",
                "explanation": "nums[0] + nums[1] = 2 + 7 = 9"
            },
            {
                "input": "nums = [3, 2, 4], target = 6",
                "output": "[1, 2]",
                "explanation": "nums[1] + nums[2] = 2 + 4 = 6"
            },
            {
                "input": "nums = [3, 3], target = 6",
                "output": "[0, 1]",
                "explanation": "nums[0] + nums[1] = 3 + 3 = 6"
            }
        ],
        "constraints": [
            "2 ≤ nums.length ≤ 10⁴",
            "-10⁹ ≤ nums[i] ≤ 10⁹",
            "-10⁹ ≤ target ≤ 10⁹",
            "Il existe exactement une solution"
        ],
        "function_name": "twoSum",
        "parameters": ["nums", "target"],
        "return_type": "List[int]"
    }

    return result

@app.post("/analyze-mission")
async def analyze_mission(request: Request):
    """Analyse la description de la mission et génère des exemples et contraintes"""
    data = await request.json()
    description = data.get('description', '')
    title = data.get('title', '')

    prompt = f"""Analyse la description de mission suivante et génère des exemples, contraintes et informations utiles.

Description: {description}
Titre: {title}

Réponds UNIQUEMENT avec ce JSON:
{{
  "examples": [
    {{"input": "exemple input", "output": "exemple output", "explanation": "explication"}}
  ],
  "constraints": ["contrainte 1", "contrainte 2"],
  "function_name": "nom_fonction",
  "parameters": ["param1", "param2"],
  "return_type": "type_retour",
  "test_cases": [
    {{"input": [1,2,3], "expected": 6}}
  ]
}}"""

    try:
        response = client.messages.create(
            model="claude-3-haiku-20240307",
            max_tokens=1000,
            messages=[{"role": "user", "content": prompt}],
        )
        raw = response.content[0].text
        # Extraire le JSON
        json_match = re.search(r'\{.*\}', raw, re.DOTALL)
        if json_match:
            return json.loads(json_match.group(0))
    except:
        pass

    # Fallback
    return {
        "examples": [
            {"input": "Exemple 1", "output": "Sortie 1", "explanation": "Explication 1"},
            {"input": "Exemple 2", "output": "Sortie 2", "explanation": "Explication 2"}
        ],
        "constraints": ["Contrainte 1", "Contrainte 2"],
        "function_name": "solution",
        "parameters": ["params"],
        "return_type": "mixed",
        "test_cases": []
    }

@app.post("/evaluate", response_model=EvaluationResponse)
def evaluate(req: EvaluationRequest):
    """
    Evaluate a code submission.
    Called by the Symfony RenduMissionController.
    """
    if not req.code or not req.code.strip():
        raise HTTPException(status_code=400, detail="Code cannot be empty")

    logger.info(f"Évaluation | lang={req.language} | mission={req.mission_title}")
    logger.info(f"Code length: {len(req.code)} characters")

    # Essayer d'utiliser Ollama
    data = None

    try:
        # Vérifier si Ollama est disponible
        r = requests.get(f"{OLLAMA_URL}/api/tags", timeout=2)
        if r.status_code == 200:
            logger.info(f"Utilisation d'Ollama avec le modèle: {MODEL_NAME}")

            # Appel à Ollama
            response = requests.post(
                f"{OLLAMA_URL}/api/generate",
                json={
                    "model": MODEL_NAME,
                    "prompt": _build_prompt(req),
                    "stream": False,
                    "temperature": 0.1,
                    "max_tokens": 500
                },
                timeout=60
            )

            if response.status_code == 200:
                result = response.json()
                raw_response = result.get("response", "")
                logger.info(f"Réponse Ollama reçue: {raw_response[:200]}")

                # Essayer d'extraire le JSON
                try:
                    # Chercher un objet JSON
                    json_match = re.search(r'\{[^{}]*\}', raw_response)
                    if json_match:
                        json_str = json_match.group(0)
                        # Nettoyer
                        json_str = json_str.replace('\\n', ' ').replace('\\t', ' ')
                        data = json.loads(json_str)
                        logger.info("JSON parsé avec succès")
                except json.JSONDecodeError as e:
                    logger.warning(f"Erreur parsing JSON: {e}")
                    data = None

    except Exception as e:
        logger.warning(f"Erreur Ollama: {e}")

    # Fallback si Ollama n'a pas fonctionné
    if not data:
        logger.info("Utilisation du fallback")
        data = _evaluate_fallback(req)

    # Calculer le score et le statut
    score = float(max(0, min(100, data.get("score", 0))))
    score_min = req.score_min if req.score_min is not None else 60
    statut = "accepte" if score >= score_min else "refuse"

    # Créer les objets TestResult
    test_results_data = data.get("test_results", [])
    test_results = []

    for t in test_results_data:
        test_results.append(TestResult(
            test_name=t.get("test_name", "–"),
            passed=bool(t.get("passed", False)),
            description=t.get("description", ""),
            details=t.get("details")
        ))

    # Générer le HTML
    resultat_html = _build_html([t.dict() for t in test_results], score, statut)

    logger.info(f"Résultat: score={score}, statut={statut}")

    return EvaluationResponse(
        score=score,
        statut=statut,
        feedback=data.get("feedback", "Aucun feedback disponible"),
        resultat_html=resultat_html,
        test_results=test_results,
        language_detected=data.get("language_detected", req.language),
        summary=data.get("summary", f"Score: {score}/100")
    )