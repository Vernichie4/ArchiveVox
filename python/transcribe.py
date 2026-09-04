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
    
    if not os.path.exists(audio_path):
        print(json.dumps({"error": f"File not found: {audio_path}"}))
        return
    
    try:
        # Load model - tiny is fast, but for Tagalog you might want base or small
        # Use 'base' for better accuracy with Tagalog
        model = whisper.load_model("base")
        
        # Transcribe with language hint for Tagalog
        # This forces Whisper to use Tagalog (tl) language model
        result = model.transcribe(
            audio_path,
            language="tl",  # Tagalog language code
            task="transcribe",
            fp16=False  # Use FP32 for better compatibility
        )
        
        # Output as JSON
        print(json.dumps({
            "success": True,
            "text": result["text"],
            "language": "tl",
            "detected_language": result.get("language", "unknown"),
            "segments": result.get("segments", [])
        }))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}))

if __name__ == "__main__":
    main()