# syntax=docker/dockerfile:1

FROM python:3.12-slim

ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    PIP_NO_CACHE_DIR=1 \
    PYTHONPATH=/app/src

WORKDIR /app

# Install uv
RUN pip install --quiet --upgrade pip && pip install --quiet uv

# Copy dependency files first for layer caching
COPY pyproject.toml ./

# Install dependencies directly (not editable package)
RUN pip install --quiet fastapi uvicorn[standard] kuzu pydantic pydantic-settings

# Copy application code
COPY . .

# Ensure Kuzu directory exists and is writable
RUN mkdir -p /data/kuzu && chmod 777 /data/kuzu

EXPOSE 8001

HEALTHCHECK --interval=10s --timeout=5s --retries=10 \
    CMD-SHELL python -c "import urllib.request; urllib.request.urlopen('http://localhost:8001/internal/health')" || exit 1

CMD ["sh", "-c", "PYTHONPATH=/app/src python -m uvicorn graph_service.main:app --host 0.0.0.0 --port 8001"]
