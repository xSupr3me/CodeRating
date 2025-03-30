<?php
/**
 * Classe Corrector pour l'évaluation des codes soumis
 * 
 * Cette classe prend en charge l'exécution sécurisée du code et la comparaison
 * avec les résultats attendus.
 */

require_once dirname(__DIR__) . '/queue/config.php';

class Corrector {
    private $submission;
    private $exercise_tests;
    private $results = [];
    private $tmp_dir;
    private $compiled_file;
    
    /**
     * Constructeur
     * 
     * @param array $submission Informations sur la soumission à corriger
     */
    public function __construct($submission) {
        $this->submission = $submission;
        $this->tmp_dir = TEMP_DIR . 'submission_' . $submission['id'] . '_' . time() . '/';
        
        // Créer un répertoire temporaire unique pour cette correction
        if (!file_exists($this->tmp_dir)) {
            mkdir($this->tmp_dir, 0755, true);
        }
        
        // Récupérer les tests pour cet exercice
        $this->loadExerciseTests();
    }
    
    /**
     * Exécuter la correction
     * 
     * @return array Résultat de la correction
     */
    public function run() {
        try {
            // Copier le fichier soumis dans le répertoire temporaire
            $source_file = $this->copySubmissionFile();
            
            // Préparer l'environnement d'exécution selon le langage
            switch (strtolower($this->submission['language_name'])) {
                case 'c':
                    $this->prepareC($source_file);
                    break;
                case 'python':
                    // Pas besoin de compilation pour Python
                    break;
                default:
                    throw new Exception("Langage non supporté: " . $this->submission['language_name']);
            }
            
            // Exécuter les tests
            $this->runTests();
            
            // Calculer le score
            $score = $this->calculateScore();
            
            // Nettoyage
            $this->cleanup();
            
            return [
                'success' => true,
                'score' => $score,
                'details' => $this->results
            ];
        } catch (Exception $e) {
            log_message('ERROR', "Erreur lors de la correction de la soumission {$this->submission['id']}: " . $e->getMessage());
            
            // Nettoyage en cas d'erreur
            $this->cleanup();
            
            return [
                'success' => false,
                'score' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Charger les tests pour l'exercice
     */
    private function loadExerciseTests() {
        $db = db_connect();
        $stmt = $db->prepare("
            SELECT test_number, arguments, expected_output
            FROM reference_tests
            WHERE exercise_id = ? AND language_id = ?
            ORDER BY test_number
        ");
        $stmt->execute([$this->submission['exercise_id'], $this->submission['language_id']]);
        $this->exercise_tests = $stmt->fetchAll();
        
        if (empty($this->exercise_tests)) {
            throw new Exception("Aucun test trouvé pour cet exercice et ce langage");
        }
    }
    
    /**
     * Copier le fichier soumis dans le répertoire temporaire
     * 
     * @return string Chemin complet vers le fichier source
     */
    private function copySubmissionFile() {
        $source_path = $this->submission['file_path'];
        $dest_file = $this->tmp_dir . basename($source_path);
        
        if (!file_exists($source_path)) {
            throw new Exception("Fichier source introuvable: $source_path");
        }
        
        if (!copy($source_path, $dest_file)) {
            throw new Exception("Impossible de copier le fichier source");
        }
        
        return $dest_file;
    }
    
    /**
     * Préparer l'environnement pour un programme C
     * 
     * @param string $source_file Chemin vers le fichier source
     */
    private function prepareC($source_file) {
        $this->compiled_file = $this->tmp_dir . 'program';
        
        // Compiler le programme C
        $compile_cmd = sprintf(
            '%s %s %s -o %s 2>&1',
            GCC_PATH,
            C_COMPILER_OPTIONS,
            escapeshellarg($source_file),
            escapeshellarg($this->compiled_file)
        );
        
        exec($compile_cmd, $output, $return_var);
        
        if ($return_var !== 0) {
            throw new Exception("Erreur de compilation: " . implode("\n", $output));
        }
    }
    
    /**
     * Exécuter tous les tests pour cette soumission
     */
    private function runTests() {
        foreach ($this->exercise_tests as $test) {
            $this->results[$test['test_number']] = [
                'arguments' => $test['arguments'],
                'expected' => $test['expected_output'],
                'passed' => false
            ];
            
            try {
                $output = $this->executeTest($test['arguments']);
                
                // Normaliser les espaces blancs et les fins de ligne
                $normalized_output = $this->normalizeOutput($output);
                $normalized_expected = $this->normalizeOutput($test['expected_output']);
                
                // Comparer le résultat
                $passed = ($normalized_output === $normalized_expected);
                $this->results[$test['test_number']]['output'] = $output;
                $this->results[$test['test_number']]['passed'] = $passed;
                
            } catch (Exception $e) {
                $this->results[$test['test_number']]['error'] = $e->getMessage();
                $this->results[$test['test_number']]['passed'] = false;
            }
        }
    }
    
    /**
     * Exécuter un test spécifique
     * 
     * @param string $arguments Arguments à passer au programme
     * @return string Sortie du programme
     */
    private function executeTest($arguments) {
        // Commande d'exécution selon le langage
        switch (strtolower($this->submission['language_name'])) {
            case 'c':
                $command = sprintf(
                    '%s %s %s %s 2>&1',
                    TIMEOUT_COMMAND,
                    MAX_EXECUTION_TIME,
                    escapeshellarg($this->compiled_file),
                    escapeshellarg($arguments)
                );
                break;
                
            case 'python':
                $source_file = $this->tmp_dir . basename($this->submission['file_path']);
                $command = sprintf(
                    '%s %s %s %s %s 2>&1',
                    TIMEOUT_COMMAND,
                    MAX_EXECUTION_TIME,
                    PYTHON_PATH,
                    escapeshellarg($source_file),
                    escapeshellarg($arguments)
                );
                break;
                
            default:
                throw new Exception("Langage non supporté");
        }
        
        // Limiter la mémoire disponible pour le processus
        $command = "ulimit -v " . (MAX_MEMORY_USAGE / 1024) . "; " . $command;
        
        // Exécuter la commande
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        
        // Vérifier les erreurs d'exécution
        if ($return_var === 124) {
            throw new Exception("Délai d'exécution dépassé (plus de " . MAX_EXECUTION_TIME . " secondes)");
        } elseif ($return_var !== 0) {
            throw new Exception("Erreur d'exécution (code $return_var): " . implode("\n", $output));
        }
        
        return implode("\n", $output);
    }
    
    /**
     * Normaliser la sortie pour la comparaison
     * 
     * @param string $output Sortie à normaliser
     * @return string Sortie normalisée
     */
    private function normalizeOutput($output) {
        // Remplacer les fins de ligne Windows par des fins de ligne Unix
        $output = str_replace("\r\n", "\n", $output);
        
        // Supprimer les espaces/tabulations en début et fin de ligne
        $lines = explode("\n", $output);
        $lines = array_map('trim', $lines);
        
        // Supprimer les lignes vides au début et à la fin
        while (count($lines) > 0 && empty($lines[0])) {
            array_shift($lines);
        }
        
        while (count($lines) > 0 && empty($lines[count($lines) - 1])) {
            array_pop($lines);
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Calculer le score final
     * 
     * @return float Pourcentage de réussite (0-100)
     */
    private function calculateScore() {
        if (empty($this->results)) {
            return 0;
        }
        
        $passed_count = 0;
        foreach ($this->results as $result) {
            if ($result['passed']) {
                $passed_count++;
            }
        }
        
        return round(($passed_count / count($this->results)) * 100, 2);
    }
    
    /**
     * Nettoyer les fichiers temporaires
     */
    private function cleanup() {
        // Supprimer le répertoire temporaire et son contenu
        $this->removeDirectory($this->tmp_dir);
        
        // Supprimer le fichier original soumis
        if (file_exists($this->submission['file_path'])) {
            unlink($this->submission['file_path']);
        }
    }
    
    /**
     * Supprimer récursivement un répertoire et son contenu
     * 
     * @param string $dir Chemin du répertoire à supprimer
     */
    private function removeDirectory($dir) {
        if (!file_exists($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
}
?>
