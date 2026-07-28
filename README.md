# Test Setup and AI Tools

This folder contains temporary and diagnostic scripts used during development.

## Essential AI tools

The project uses the following services for speech and text processing:

### 1. ASR (Automatic Speech Recognition)
Use Whisper for audio transcription.

Recommended options:
- OpenAI Whisper via Python
- Faster-Whisper for better performance

Install example:
```bash
pip install openai-whisper
```

If you want a faster option:
```bash
pip install faster-whisper
```

### 2. OCR (Optical Character Recognition)
Use Tesseract OCR for extracting text from images.

Windows installation:
1. Download Tesseract from: https://github.com/UB-Mannheim/tesseract/wiki
2. Install it and add the executable path to your system PATH.
3. Verify installation:
```bash
tesseract --version
```

### 3. TTS (Text-to-Speech)
Use a TTS engine such as Coqui TTS or pyttsx3 for voice output.

Install example:
```bash
pip install TTS
```

Alternative lightweight option:
```bash
pip install pyttsx3
```

## Recommended environment
- Python 3.10 or newer
- XAMPP / Apache for the web app
- MySQL database for the project

## Windows setup steps

### Install Python
1. Download Python from https://www.python.org/downloads/
2. During installation, check "Add Python to PATH".
3. Verify it works:
```bash
python --version
pip --version
```

### Install Whisper and OCR dependencies
```bash
pip install openai-whisper
pip install faster-whisper
```

### Install TTS dependency
```bash
pip install TTS
```

### Install Tesseract OCR
1. Download Tesseract from https://github.com/UB-Mannheim/tesseract/wiki
2. Install it and add the install folder to PATH.
3. Confirm it is available:
```bash
tesseract --version
```

## Recommended stack for your team
- ASR: Whisper or Faster-Whisper
- OCR: Tesseract
- TTS: Coqui TTS or pyttsx3
- Backend: PHP + MySQL
- Frontend: HTML/CSS/JavaScript

## Quick checks
- ASR test scripts: tests/api and tests/ocr
- OCR test script: tests/ocr/test_ocr_simple.php
- Upload and OCR flow: tests/ocr/upload_test.php

## Notes
- Some tools may require additional system packages depending on your OS.
- On Windows, make sure the installed executables are accessible from the command line.
- If you plan to share the project publicly, keep secrets such as database credentials out of the repository.
