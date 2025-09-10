#!/bin/bash
sudo apt update
sudo apt install -y centreon-plugin\*

python3 -m venv . 
source bin/activate 
pip install --upgrade pip 
pip install fastapi uvicorn 

# Lancer l’API
uvicorn api_control:app --host 0.0.0.0 --port 8000
