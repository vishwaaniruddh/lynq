# Workspace Rules

## Mobile API Architecture Rule
- **CRITICAL**: If any additional or custom API endpoints are required for the mobile application, ALWAYS create and place them in `api/app/` (e.g. `api/app/feasibility.php`, `api/app/sites.php`, `api/app/...`).
- Do not scatter mobile-specific API endpoints in random root directories. Keep all mobile app backend endpoints organized within `api/app/`.
