# frontend_web — Panel web C22

@../.agents/rules/frontend-template-policy.md

- Catálogo de referencia local: `template_reference/admin-full-version/src/` (ignorado por git; si no existe, extraer `admin-full-version.zip` de la raíz).
- Gestor de paquetes: pnpm. `pnpm build` escribe en `../backend/public/`.
- Páginas en `src/pages/` (rutas automáticas, ver `typed-router.d.ts`); navegación en `src/navigation/`.
