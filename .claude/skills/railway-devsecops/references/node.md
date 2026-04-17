# Node.js / TypeScript / Vue / React — Docker Reference

## Recommended base images

| Runtime | Builder stage | Final stage |
|---------|--------------|-------------|
| Node.js / TS | `node:22-alpine` | `node:22-alpine` |
| Static SPA (Vue/React) | `node:22-alpine` | `nginx:1.27-alpine` |

Pin to a specific patch version: `node:22.x-alpine3.20`, `nginx:1.27.0-alpine3.19`.

---

## Vue / React SPA (static build → Nginx)

SPAs produce static files after `npm run build`. Serve them from Nginx — no Node.js
at runtime, smallest possible image.

```dockerfile
# ── Build stage ──────────────────────────────────────────────────────────────
FROM node:22-alpine AS builder
WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build
# Output: dist/ (Vite default) or build/ (Create React App)

# ── Runtime stage (Nginx) ─────────────────────────────────────────────────────
FROM nginx:1.27-alpine AS runner

# Remove default config
RUN rm /etc/nginx/conf.d/default.conf

# Custom nginx config (see below)
COPY docker/nginx.conf /etc/nginx/conf.d/app.conf

# Copy built assets
COPY --from=builder /app/dist /usr/share/nginx/html
# For CRA: COPY --from=builder /app/build /usr/share/nginx/html

# Nginx runs as 'nginx' user by default — no root needed
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s CMD wget -qO- http://localhost/health || exit 1

CMD ["nginx", "-g", "daemon off;"]
```

**docker/nginx.conf** (required for Vue Router / React Router history mode):
```nginx
server {
    listen ${PORT:-80};
    server_name _;
    root /usr/share/nginx/html;
    index index.html;

    # SPA fallback — send all routes to index.html
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Healthcheck endpoint
    location /health {
        return 200 'ok';
        add_header Content-Type text/plain;
    }
}
```

Railway note: Railway injects `$PORT` and expects the app to listen on it. Use
`envsubst` to substitute it into the Nginx config at startup if needed, or hardcode
port 80 and let Railway handle the mapping.

---

## Node.js / TypeScript API backend (Express, Fastify, NestJS)

```dockerfile
# ── Build stage ──────────────────────────────────────────────────────────────
FROM node:22-alpine AS builder
WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build
# Compiles TS → dist/

# ── Runtime stage ─────────────────────────────────────────────────────────────
FROM node:22-alpine AS runner
WORKDIR /app

RUN addgroup -S appgroup && adduser -S appuser -G appgroup

# Install only production deps in runtime stage
COPY --chown=appuser:appgroup package*.json ./
RUN npm ci --omit=dev && npm cache clean --force

COPY --from=builder --chown=appuser:appgroup /app/dist ./dist

USER appuser
ENV NODE_ENV=production
EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD wget -qO- http://localhost:${PORT:-3000}/health || exit 1

CMD ["node", "dist/main.js"]
```

## Node.js API (no build step — plain JS)

```dockerfile
FROM node:22-alpine
WORKDIR /app

RUN addgroup -S appgroup && adduser -S appuser -G appgroup

COPY --chown=appuser:appgroup package*.json ./
RUN npm ci --omit=dev && npm cache clean --force

COPY --chown=appuser:appgroup . .

USER appuser
ENV NODE_ENV=production

CMD ["node", "src/index.js"]
```

## Vue/React + Node.js API (monorepo / full-stack)

If frontend and backend live in the same repo, build them in separate stages and
either:
- Serve static files from the Node.js process itself (Express `static` middleware)
- Deploy as two separate Railway services (recommended for production)

For the "serve from Express" approach:
```dockerfile
FROM node:22-alpine AS frontend-builder
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm ci
COPY frontend/ .
RUN npm run build

FROM node:22-alpine AS backend-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM node:22-alpine AS runner
WORKDIR /app
RUN addgroup -S appgroup && adduser -S appuser -G appgroup
COPY --chown=appuser:appgroup package*.json ./
RUN npm ci --omit=dev && npm cache clean --force
COPY --from=backend-builder --chown=appuser:appgroup /app/dist ./dist
COPY --from=frontend-builder --chown=appuser:appgroup /app/frontend/dist ./public
USER appuser
ENV NODE_ENV=production
CMD ["node", "dist/server.js"]
```

## Railway PORT note

Railway sets `PORT` at runtime. Bind to it:
```ts
const port = process.env.PORT ?? 3000;
app.listen(port, '0.0.0.0', () => console.log(`Listening on :${port}`));
```
`0.0.0.0` is required — `localhost`/`127.0.0.1` is unreachable outside the container.

For NestJS:
```ts
await app.listen(process.env.PORT ?? 3000, '0.0.0.0');
```

## .dockerignore additions for Node.js / TS

```
node_modules/
dist/
build/
.next/
.nuxt/
npm-debug.log*
yarn-debug.log*
yarn-error.log*
.pnpm-debug.log*
.eslintrc*
.prettierrc*
jest.config.*
vitest.config.*
*.test.*
*.spec.*
__tests__/
coverage/
.nyc_output/
*.stories.*
storybook-static/
```
