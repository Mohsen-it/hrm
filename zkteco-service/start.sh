#!/bin/bash

# ZKTeco Microservice Startup Script

echo "🚀 Starting ZKTeco Microservice..."

# Find python3 (use explicit path if available)
PYTHON3_CMD="python3"
if [ -f "/usr/bin/python3" ]; then
    PYTHON3_CMD="/usr/bin/python3"
elif command -v python3 &> /dev/null; then
    PYTHON3_CMD=$(command -v python3)
fi

# Check if virtual environment exists
if [ ! -d "venv" ]; then
    echo "📦 Creating virtual environment using $PYTHON3_CMD..."
    "$PYTHON3_CMD" -m venv venv
    if [ $? -ne 0 ]; then
        echo "❌ Failed to create virtual environment!"
        exit 1
    fi
fi

# Check if python exists in venv
if [ ! -f "venv/bin/python" ] && [ ! -f "venv/bin/python3" ]; then
    echo "❌ Python not found in venv!"
    echo "🔄 Recreating venv using $PYTHON3_CMD..."
    rm -rf venv
    "$PYTHON3_CMD" -m venv venv
    if [ $? -ne 0 ]; then
        echo "❌ Failed to create virtual environment!"
        exit 1
    fi
fi

# Activate virtual environment
echo "🔧 Activating virtual environment..."
source venv/bin/activate

# Install/Update dependencies
echo "📥 Installing dependencies..."
pip install -q -r requirements.txt

# Check if .env exists
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo "📝 Creating .env from .env.example..."
        cp .env.example .env
    fi
fi

# Start the service
echo "✅ Starting service on port 5000..."
echo ""

# Determine which python to use
PYTHON_CMD="venv/bin/python"
if [ ! -f "$PYTHON_CMD" ]; then
    PYTHON_CMD="venv/bin/python3"
fi

if [ ! -f "$PYTHON_CMD" ]; then
    echo "❌ Python not found in venv!"
    exit 1
fi

# Use python from venv
"$PYTHON_CMD" app.py

