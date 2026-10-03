import sys
import json
import os
import shutil

# Suppress Whisper progress bars by redirecting stderr
import io
sys.stderr = io.StringIO()

# Set cache directory BEFORE importing whisper to avoid permission issues
cache_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'uploads', 'temp', '.whisper_cache')
os.makedirs(cache_dir, exist_ok=True)
os.environ['HF_HOME'] = cache_dir
os.environ['XDG_CACHE_HOME'] = cache_dir
os.environ['TRANSFORMERS_CACHE'] = cache_dir

import whisper

def ensure_ffmpeg_available():
    """Make ffmpeg discoverable when PHP launches Python without a full user PATH."""
    # Check current PATH
    ffmpeg_in_path = shutil.which("ffmpeg")
    if ffmpeg_in_path:
        return

    # If not in PATH, try common locations (local development only)
    # Note: Production (Railway) will have ffmpeg in PATH
    candidate_dirs = [
        "/usr/bin",  # Linux/Railway
        "/usr/local/bin",  # Linux/Railway
        "/opt/whisper-venv/bin",  # Docker virtual environment
        r"C:\ffmpeg\bin",  # Windows
        r"C:\Program Files\ffmpeg\bin",  # Windows
        # Dynamic Winget installation path (works for any user)
        rf"C:\Users\{os.getenv('USERNAME')}\AppData\Local\Microsoft\WinGet\Packages\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\ffmpeg-9.0.2-full_build\bin",
    ]

    for directory in candidate_dirs:
        ffmpeg_exe = os.path.join(directory, "ffmpeg.exe" if os.name == 'nt' else "ffmpeg")
        if os.path.exists(ffmpeg_exe):
            os.environ["PATH"] = directory + os.pathsep + os.environ.get("PATH", "")
            return


ensure_ffmpeg_available()

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No audio file provided"}))
        return
    
    audio_path = sys.argv[1]
    
    # Read the language argument passed from PHP
    target_language = None
    if len(sys.argv) > 2 and sys.argv[2] != 'auto':
        target_language = sys.argv[2]
    
    if not os.path.exists(audio_path):
        print(json.dumps({"error": f"File not found: {audio_path}"}))
        return
    
    try:
        model = whisper.load_model("base")

        # Build transcription arguments dynamically
        transcribe_args = {
            "task": "transcribe",
            "fp16": False,
            "verbose": False  # Suppress progress output
        }

        # Force Whisper to use the specific language model to prevent hallucinations
        if target_language:
            transcribe_args["language"] = target_language

        result = model.transcribe(audio_path, **transcribe_args)

        print(json.dumps({
            "success": True,
            "text": result["text"],
            "language": target_language if target_language else result.get("language", "unknown"),
            "detected_language": result.get("language", "unknown"),
            "segments": result.get("segments", [])
        }))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}))

if __name__ == "__main__":
    main()