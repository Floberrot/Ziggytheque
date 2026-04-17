# ── Build stage ──────────────────────────────────────────────────────────────
FROM node:20.14-alpine3.20 AS builder
WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build
# Output: dist/

# ── Runtime stage (Nginx) ─────────────────────────────────────────────────────
FROM nginx:1.27.0-alpine3.19 AS runner

RUN rm /etc/nginx/conf.d/default.conf

COPY docker/nginx.conf /etc/nginx/conf.d/app.conf
COPY --from=builder /app/dist /usr/share/nginx/html

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s \
  CMD wget -qO- http://localhost/health || exit 1

CMD ["nginx", "-g", "daemon off;"]
