# CLAUDE.md — F1 Tracker

Fichier d'instructions pour Claude. À placer à la racine du projet.

---

## Présentation du projet

**F1 Tracker** est un projet personnel en PHP/JS qui permet d'afficher le calendrier des courses de Formule 1, le détail des week-ends de Grand Prix et un live timing en temps réel. Il est inspiré du F1 Live Timing officiel, mais n'a aucun lien avec Formula 1, la FIA, les équipes ou les pilotes.

---

## Structure du projet

```
F1_Tracker/
├── index.php           # Page d'accueil / point d'entrée
├── grandprix.php       # Détail d'un week-end de GP (sessions, horaires)
├── calendar.php        # Calendrier des courses
├── calendar_year.php   # Vue calendrier par saison
├── livetiming.php      # Page live timing
├── credits.php         # Crédits
├── style.css           # Feuille de style globale
├── includes/
│   ├── header.php      # En-tête commun à toutes les pages
│   └── footer.php      # Pied de page commun
├── api/                # Endpoints JSON internes (backend consommé par le JS)
│   ├── years.php             # Saisons disponibles
│   ├── meetings.php          # ?year= — GP d'une saison
│   ├── meeting.php           # ?meeting_key= — détail d'un GP + sessions
│   ├── livetiming_focus.php  # ?year= (optionnel) — session focus + statut
│   └── livetiming_status.php # ?session_key= — météo, état piste, tours
├── lib/
│   ├── openf1.php      # ← Tous les appels à l'API F1 (OpenF1) + services métier (f1_get_*)
│   ├── api.php         # api_response() (JSON { data, error } + en-têtes) et validation des paramètres GET
│   ├── datetime.php    # Helpers dates et heures
│   └── text.php        # Helpers texte / formatage
├── script/
│   ├── script.js       # Zoom image circuit (chargé globalement via footer.php)
│   ├── countdown.js    # Compte à rebours live timing (chargé par livetiming.php)
│   └── livetiming.js   # Polling météo / état piste / tours (chargé par livetiming.php)
└── img/                # Images statiques
    └── favicon/        # Favicons + site.webmanifest (liés dans includes/header.php)
```

---

## Stack technique

- **Back-end :** PHP (pas de framework)
- **Front-end :** HTML, CSS vanilla, JavaScript vanilla
- **API :** OpenF1 (via `lib/openf1.php`)
- **Pas de base de données** — toutes les données viennent de l'API

---

## Conventions de code

- Les appels API sont **exclusivement** centralisés dans `lib/openf1.php`. Ne jamais appeler l'API directement depuis une page PHP ou un script JS.
- **Séparation back / front** : la logique métier (appels OpenF1 + calculs) est écrite une seule fois, sous forme de services `f1_get_*()` dans `lib/openf1.php`. Les pages PHP (rendu serveur) et les endpoints `api/*.php` (JSON) ne font qu'appeler ces services ; les pages ne gardent que le formatage d'affichage.
- Les endpoints `api/` valident leurs paramètres, appellent un service puis répondent via `api_response()` (`{ "data": ..., "error": null|"message" }`, clés en `snake_case`, dates en ISO 8601). Le JS ne parle qu'à `api/`, jamais à OpenF1.
- Les fichiers de `lib/` sont inclus avec `require_once` (`lib/openf1.php` inclut déjà `datetime.php` et `text.php`).
- Les fonctions utilitaires de date/heure vont dans `lib/datetime.php`, celles de texte dans `lib/text.php`.
- Le HTML commun (header, footer) est inclus via `includes/`. Toute nouvelle page doit inclure ces deux fichiers.
- Le CSS global est dans `style.css` à la racine. Pas de CSS inline (`style=""`, `<style>`) ni de JS inline (`onclick=""`, `<script>…</script>`) : la CSP du `.htaccess` les bloque. Toute ressource externe (CDN, police…) doit être ajoutée explicitement à la CSP.
- Les scripts JS sont dans `script/`. Chaque fichier a une responsabilité unique. Les scripts globaux sont chargés via `footer.php`, les scripts spécifiques à une page sont inclus directement dans cette page.
- Le code est rédigé **en français** (commentaires, noms de variables, messages utilisateur).

---

## Fonctionnalités

| Fonctionnalité | État |
|---|---|
| Sélection de saison | ✅ Terminé |
| Calendrier des GP (mise à jour automatique après chaque course) | ✅ Terminé |
| Détail d'un week-end (sessions, horaires) | ✅ Terminé |
| Live timing — compte à rebours | ✅ Terminé |
| Live timing — météo | ✅ Terminé |
| Live timing — état de piste | ✅ Terminé |
| Suivi en live — mini carte & positions voitures | 🔲 À faire |
| Suivi en live — suivi d'un pilote précis | 🔲 À faire |

---

## Comportement attendu de Claude

- **Respecter la structure existante** : ne pas proposer de nouveaux fichiers sans raison valable, et toujours placer le code au bon endroit selon les conventions ci-dessus.
- **Frameworks autorisés** : l'utilisation de frameworks JS/CSS (ex. Tailwind, Bootstrap, Alpine.js…) ou de bibliothèques PHP (via Composer) est autorisée si cela apporte une réelle valeur ajoutée. Privilégier des solutions légères et cohérentes avec le projet existant.
- **Centraliser les appels API** : tout nouveau besoin de données F1 passe par `lib/openf1.php`.
- **Cohérence visuelle** : tout ajout de style doit s'appuyer sur `style.css` et rester cohérent avec l'existant.
- **Langue** : les réponses, commentaires de code et messages utilisateur sont en **français**.
- **Projet non officiel** : ne pas utiliser les logos, marques ou assets officiels F1/FIA sans vérification préalable.

---

## Rappels utiles

- L'API utilisée est **OpenF1** (open-source, gratuite) — documentation : https://openf1.org
- Les données de session, météo, positions et timing viennent toutes de cette API.
- Ce projet est personnel et non commercial.

## Sécurité

- Aucune information sensible en clair dans le code autre que celle voulue (nom, prénom).
- Pas de clé d'API en clair — utiliser un fichier .env ou un fichier de config exclu du dépôt.
- Modifier le .gitignore si nécessaire pour protéger les fichiers sensibles.
- Analyser, corriger et éviter les failles de sécurité (XSS, injections, exposition de données, etc.).