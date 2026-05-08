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
├── lib/
│   ├── openf1.php      # ← Tous les appels à l'API F1 (OpenF1)
│   ├── datetime.php    # Helpers dates et heures
│   └── text.php        # Helpers texte / formatage
├── script/
│   ├── script.js       # Zoom image circuit (chargé globalement via footer.php)
│   └── countdown.js    # Compte à rebours live timing (chargé par livetiming.php)
└── img/                # Images statiques
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
- Les fonctions utilitaires de date/heure vont dans `lib/datetime.php`, celles de texte dans `lib/text.php`.
- Le HTML commun (header, footer) est inclus via `includes/`. Toute nouvelle page doit inclure ces deux fichiers.
- Le CSS global est dans `style.css` à la racine. Pas de CSS inline sauf cas exceptionnel justifié.
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