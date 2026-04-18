# ── Stage 1: Build ────────────────────────────────────────────────────────────
FROM node:22-alpine AS builder

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# VITE_API_BASE_URL is intentionally left empty: the frontend uses relative /api calls
# which nginx proxies to $BACKEND_URL at runtime. No value needed at build time.
ARG VITE_API_BASE_URL
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}

COPY . .
RUN npm run build
# Output: /app/dist/

# ── Stage 2: Production ───────────────────────────────────────────────────────
FROM nginx:alpine AS prod

COPY --from=builder /app/dist /usr/share/nginx/html

COPY nginx.conf.template /etc/nginx/templates/default.conf.template

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
