# Tax Saathi deployment — Vercel + Render + MongoDB Atlas

## Production architecture

- **Vercel:** React 19 / Vite static frontend. Project **Root Directory: `client`**, framework **Vite**, install `npm ci`, build `npm run build`, output `dist`.
- **Render:** Existing Node/Express backend service at `https://taxsaathi-3q5k.onrender.com`; its API health endpoint is `/api/health`.
- **MongoDB Atlas:** Existing **Taxsaathi Production** project and **taxsaathi-prod** cluster. The `MONGODB_URI` environment variable stays exclusively on Render (never Vercel).
- **GitHub:** `thecleverengineers/taxsaathi-production`; `main` is the production branch.

## Same-origin API proxy

`client/vercel.json` rewrites frontend paths to the existing Render backend:

| Frontend request | Backend destination |
| --- | --- |
| `/api/*` | Render `/api/*` (including login, OTP, uploads, payments, Server-Sent Events, web push subscriptions) |
| `/legacy-assets/*` | Render legacy uploads |
| `/uploads/*` | Render `/legacy-assets/uploads/*` |
| `/payment-assets/*` | Render payment files |
| `/sitemap.xml` | Render-generated sitemap |

Everything else falls back to `/index.html` for deep-linked React Router pages. Real static files such as `/push-sw.js`, images and built JS/CSS remain on Vercel.

**Do not set `VITE_API_URL` on Vercel for this architecture.** Leaving it unset allows the frontend to use same-origin `/api` requests. This preserves the existing HTTP-only, SameSite=Lax session cookies, ensures notification SSE uses the same origin, and reduces cross-site browser restrictions. The existing `api()` helper continues to work without modification.

## Render configuration

Keep `MONGODB_URI`, `JWT_SECRET`, SMS/payment secrets, `COOKIE_SECURE=true` and all other backend-only secrets on Render. Do not duplicate them in Vercel.

When Vercel's final assigned production hostname is known:
- Set `CORS_ORIGIN` to the exact production frontend origin (for example, `https://<assigned-hostname>`). Add any owned custom frontend domain as a comma-separated entry. Do **not** use `*` with credentialed requests.
- Set `PUBLIC_URL` to the canonical frontend origin **only after** the frontend is serving correctly. This affects generated sitemap URLs.
- Leave `COOKIE_DOMAIN` unset, so the proxied `Set-Cookie` header becomes host-only on the public frontend hostname.
- Do not remove Render's existing frontend fallback until Vercel is verified, to retain rollback access.

The Vercel reverse proxy does not change which infrastructure processes API requests; Render remains responsible for API authentication, persistence, notifications, and payments.

## Deployment validation checklist

1. Validate GitHub CI (`npm run check`, `npm test`, `npm run build`).
2. Confirm the Vercel project's Git repository, Root Directory `client`, build output `dist`, and production target `main`.
3. Open `https://<vercel-production-hostname>/`, `/auth`, `/register`, `/forgot-password`, `/services`, `/dashboard`.
4. Check that `GET /api/health` on the Vercel hostname responds from Render and that Render is in MongoDB mode.
5. Test real account login, logout, OTP registration and password reset, authorization boundaries, service catalog, payment workflows, protected documents, downloads, uploads and real-time notifications. Do not perform real financial transactions purely as a smoke test.
6. Verify the browser's `taxsaathi_token` cookie has Secure and HttpOnly flags, and the live frontend includes `/push-sw.js`.
7. Confirm deep links reload without 404, and ensure file URLs and image requests still resolve.
8. Verify Render production logs and error rates; check Vercel deployment logs and mobile rendering.

## Rollback

The existing Render URL remains a temporary independent frontend fallback unless the backend is later configured in API-only mode. A Vercel-only rollback should not modify or migrate Atlas data.
