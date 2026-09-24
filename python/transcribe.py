import whisper
import sys
import json
import os
import shutil


def ensure_ffmpeg_available():
    """Make ffmpeg discoverable when PHP launches Python without a full user PATH."""
    if shutil.which("ffmpeg"):
        return

    candidate_dirs = [
        r"C:\ffmpeg\ffmpeg-8.1.2-full_build\bin",
        r"C:\Program Files\ffmpeg\bin",
        r"C:\Program Files (x86)\ffmpeg\bin",
    ]

    for directory in candidate_dirs:
        ffmpeg_exe = os.path.join(directory, "ffmpeg.exe")
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
            "fp16": False
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