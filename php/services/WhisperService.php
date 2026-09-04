<?php
/**
 * WhisperService - Handles audio transcription using OpenAI's Whisper
 * 
 * Location: php/services/WhisperService.php
 */

class WhisperService
{
    private $pythonPath;
    private $scriptPath;
    private $language;
    
    // Supported languages for validation
    private const SUPPORTED_LANGUAGES = ['tl', 'fil', 'en', 'ceb', 'ilo', 'auto'];

    public function __construct(string $language = 'auto')
    {
        // Prefer the known interpreter path, but fall back to the Windows launcher if needed.
        $primaryPython = 'C:\\Users\\aleli\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe';
        $launcherPython = 'py -3.14';

        $this->pythonPath = file_exists($primaryPython) ? $primaryPython : $launcherPython;
        
        // Path to the Python transcription script
        $this->scriptPath = __DIR__ . '/../../python/transcribe.py';
        $this->language = $language;
        $this->language = $this->validateLanguage($language);
    }

    private function validateLanguage(string $language): string {
        $lang = strtolower(trim($language));
        if (in_array($lang, self::SUPPORTED_LANGUAGES)) {
            return $lang;
        }
        return 'auto'; // Default to auto-detection for unsupported languages
    }
    
    /**
     * Transcribe an audio file using Whisper
     * 
     * @param string $audioPath Full path to the audio file
     * @return array Array with 'success', 'text', and 'language' or 'error'
     */
     public function transcribeAudio(string $audioPath, ?string $language = null): array
    {
        // Use the language passed or fall back to the constructor default
        $lang = $language ?? $this->language;
        
        // Check if file exists
        if (!file_exists($audioPath)) {
            return [
                'success' => false,
                'error' => 'Audio file not found: ' . $audioPath
            ];
        }

        if (!file_exists($this->scriptPath)) {
            return [
                'success' => false,
                'error' => 'Python script not found: ' . $this->scriptPath
            ];
        }
        
        // Build a safe command for Windows paths
        $command = $this->pythonPath;
        if ($this->pythonPath !== 'py -3.14') {
            $command = escapeshellarg($this->pythonPath);
        }

        $command .= ' ' . escapeshellarg($this->scriptPath)
            . ' ' . escapeshellarg($audioPath);
        
        // Add language parameter if specified
        if ($lang !== 'auto' && $lang !== null) {
            $command .= ' ' . escapeshellarg($lang);
        }
        
        // Execute and capture output
        $output = shell_exec($command . ' 2>&1');
        if ($output === null) {
            return [
                'success' => false,
                'error' => 'Transcription command returned no output',
                'command' => $command,
            ];
        }
        
        // Parse JSON response
        $result = json_decode(trim($output), true);
        if (!is_array($result)) {
            $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];
            for ($i = count($lines) - 1; $i >= 0; $i -= 1) {
                $candidate = trim($lines[$i]);
                if ($candidate === '') {
                    continue;
                }

                $decoded = json_decode($candidate, true);
                if (is_array($decoded)) {
                    $result = $decoded;
                    break;
                }
            }
        }
        
        if (!is_array($result)) {
            return [
                'success' => false,
                'error' => 'Failed to parse transcription output',
                'raw_output' => substr($output, 0, 4000)
            ];
        }
        
        return $result;
    }
    
    /**
     * Transcribe and save to database
     * 
     * @param string $audioPath Full path to the audio file
     * @param int $activityId The reading activity ID
     * @param string|null $language Language code
     * @return array Result with transcription data
     */
    public function transcribeAndSave(string $audioPath, int $activityId, ?string $language = null): array
    {
        global $pdo;
        
        // Transcribe
        $result = $this->transcribeAudio($audioPath, $language);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Save transcription to database
        try {
            $stmt = $pdo->prepare('
                UPDATE reading_activity 
                SET transcript = :transcript, 
                    language = :language,
                    activity_status = "Completed"
                WHERE activity_id = :activity_id
            ');
            
            $stmt->execute([
                ':transcript' => $result['text'],
                ':language' => $result['language'] ?? 'unknown',
                ':activity_id' => $activityId
            ]);
            
            return [
                'success' => true,
                'text' => $result['text'],
                'language' => $result['language'] ?? 'unknown',
                'message' => 'Transcription saved successfully'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Set the transcription language
     * 
     * @param string $language Language code (e.g., 'tl', 'en', 'auto')
     * @return self
     */
    public function setLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }
}