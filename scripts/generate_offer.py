import json
import re
import sys
import unicodedata
from typing import Any

from ollama import chat


MODEL_NAME = "gemma2:2b"
IGNORED_TITLE_TOKENS = {
    "senior",
    "junior",
    "confirme",
    "confirmee",
    "expert",
    "experte",
}


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


def normalize_text(value: str) -> str:
    normalized = unicodedata.normalize("NFKD", value or "")
    return normalized.encode("ascii", "ignore").decode("ascii").lower()


def build_fallback_title(original_title: str, type_contrat: str, localisation: str) -> str:
    parts = [original_title]
    original_lower = normalize_text(original_title)

    if type_contrat and normalize_text(type_contrat) not in original_lower:
        parts.append(type_contrat)
    if localisation and normalize_text(localisation) not in original_lower:
        parts.append(localisation)

    return " - ".join(part for part in parts if part)


def build_title_prompt(payload: dict[str, Any]) -> str:
    titre = str(payload.get("titre", "")).strip()
    entreprise = str(payload.get("entreprise", "")).strip()
    localisation = str(payload.get("localisation", "")).strip()
    type_contrat = str(payload.get("typeContrat", "")).strip()
    experience = str(payload.get("experienceRequise", "")).strip()
    niveau = str(payload.get("niveauQualification", "")).strip()

    return f"""
Tu es un expert RH spécialisé dans la rédaction d'offres d'emploi en français pour la Tunisie.

Ta mission:
- améliorer uniquement le titre de l'offre
- proposer un titre plus professionnel, plus clair et plus attractif
- garder un style crédible et adapté à une vraie annonce

Données disponibles:
- Titre actuel: {titre or "Non renseigné"}
- Entreprise: {entreprise or "Non renseigné"}
- Localisation: {localisation or "Non renseigné"}
- Type de contrat: {type_contrat or "Non renseigné"}
- Expérience requise: {experience or "Non renseigné"}
- Niveau de qualification: {niveau or "Non renseigné"}

Contraintes:
- réponds uniquement avec du JSON brut
- n'utilise pas de markdown
- n'utilise pas de balises ```json
- n'ajoute aucun texte avant ou après le JSON
- le titre doit être court, professionnel et naturel
- pas d'émojis
- pas de guillemets dans le titre
- ne retourne jamais de modèle générique comme [Domaine/Secteur], [Poste], [Entreprise]
- conserve les informations concrètes déjà présentes comme Symfony, CDI, Tunis, senior
- si le titre actuel est déjà pertinent, améliore seulement sa formulation

Format exact:
{{
  "titre": "Titre amélioré"
}}
"""


def build_full_offer_prompt(payload: dict[str, Any]) -> str:
    keywords = str(payload.get("keywords", "")).strip()

    return f"""
Tu es un expert en recrutement en Tunisie.
Génère un titre d'offre d'emploi et une description professionnelle en français.

Mots-clés / description fournie: {keywords}

Contraintes:
- Réponds uniquement avec du JSON brut
- N'utilise pas de markdown
- N'utilise pas de balises ```json
- N'ajoute aucun texte avant ou après le JSON

Format exact:
{{
  "titre": "Titre attractif et professionnel",
  "description": "Description complète, attractive, avec les responsabilités et exigences."
}}
"""


def generate_title(payload: dict[str, Any]) -> dict[str, str]:
    result = call_model(build_title_prompt(payload))
    titre = str(result.get("titre", "")).strip()
    original_title = str(payload.get("titre", "")).strip()
    type_contrat = str(payload.get("typeContrat", "")).strip()
    localisation = str(payload.get("localisation", "")).strip()

    if not titre:
        titre = original_title or "Développeur Symfony Senior - CDI - Tunis"

    if "[" in titre or "]" in titre:
        titre = original_title

    original_lower = normalize_text(original_title)
    suggested_lower = normalize_text(titre)
    important_tokens = [
        token
        for token in re.findall(r"[a-z0-9+#]+", original_lower)
        if len(token) >= 4 and token not in IGNORED_TITLE_TOKENS
    ]
    overlap_count = sum(1 for token in set(important_tokens) if token in suggested_lower)

    if "developpeur" in original_lower and all(token not in suggested_lower for token in ("developpeur", "developer")):
        titre = build_fallback_title(original_title, type_contrat, localisation)

    if "symfony" in original_lower and "symfony" not in normalize_text(titre):
        titre = build_fallback_title(original_title, type_contrat, localisation)

    if original_title and important_tokens and overlap_count < min(2, len(set(important_tokens))):
        titre = build_fallback_title(original_title, type_contrat, localisation)

    return {"titre": titre}


def generate_offer(payload: dict[str, Any]) -> dict[str, str]:
    result = call_model(build_full_offer_prompt(payload))
    titre = str(result.get("titre", "")).strip()
    description = str(result.get("description", "")).strip()

    if not titre:
        keywords = str(payload.get("keywords", "")).strip()
        titre = f"Développeur / Poste - {keywords[:30]}".strip()

    if not description:
        description = "Description générée automatiquement."

    return {
        "titre": titre,
        "description": description,
    }


def main() -> None:
    mode = sys.argv[1] if len(sys.argv) > 1 else "full"
    raw_payload = sys.argv[2] if len(sys.argv) > 2 else "{}"

    try:
        payload = json.loads(raw_payload)
    except json.JSONDecodeError:
        payload = {"keywords": raw_payload}

    if mode == "title":
        result = generate_title(payload)
    else:
        result = generate_offer(payload)

    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
