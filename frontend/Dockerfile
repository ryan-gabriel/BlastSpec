# syntax=docker/dockerfile:1

FROM node:22-alpine AS builder

WORKDIR /app

# Copy package files first for layer caching
COPY package.json ./

# Install dependencies
RUN npm install

# Copy application code
COPY . .

ARG NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
ENV NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL

RUN npm run build

FROM node:22-alpine AS runner

WORKDIR /app

ENV NODE_ENV=production \
    HOSTNAME=0.0.0.0 \
    PORT=3000

COPY --from=builder /app/.next/standalone ./
COPY --from=builder /app/.next/static ./.next/static
RUN mkdir -p ./public

EXPOSE 3000

HEALTHCHECK --interval=10s --timeout=5s --retries=10 \
    CMD wget --no-verbose --tries=1 --spider http://127.0.0.1:3000/ || exit 1

CMD ["node", "server.js"]
