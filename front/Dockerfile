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

# Runs as the image's unprivileged `nginx` user, still on port 80 (where Railway routes):
# the binary carries CAP_NET_BIND_SERVICE; the pid file moves to /tmp and the `user`
# directive (meant for a root master) goes; the user owns what nginx writes at start —
# conf.d (the config rendered from the template) and its temp files in /var/cache/nginx.
RUN apk add --no-cache --virtual .setcap libcap-setcap && \
    setcap cap_net_bind_service=+ep /usr/sbin/nginx && \
    apk del .setcap && \
    sed -i -e '/^user /d' -e 's,^pid .*;,pid /tmp/nginx.pid;,' /etc/nginx/nginx.conf && \
    grep -q '^pid /tmp/nginx.pid;' /etc/nginx/nginx.conf && \
    chown -R nginx:nginx /etc/nginx/conf.d /var/cache/nginx

USER nginx

# Must be overridden in Railway with the backend service URL.
# If unset, envsubst leaves ${BACKEND_URL} as a literal and nginx fails to start.
ENV BACKEND_URL=http://BACKEND_URL_NOT_SET

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
