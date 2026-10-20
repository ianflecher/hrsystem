FROM node:20-bookworm-slim

WORKDIR /app
COPY package.json server.js ./
COPY public ./public

ENV PORT=10000
EXPOSE 10000

USER node
CMD ["node", "server.js"]
