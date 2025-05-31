FROM node:22-alpine

WORKDIR /var/www/html/itsmint

# Install dependencies for better performance
RUN apk add --no-cache \
    git \
    python3 \
    make \
    g++

# Copy package files
COPY package*.json ./

# Install dependencies (use npm install instead of ci for flexibility)
RUN npm install

# Copy project files
COPY . .

# Expose Vite dev server port
EXPOSE 5173

# Default command (can be overridden in compose.yml)
CMD ["npm", "run", "dev"] 