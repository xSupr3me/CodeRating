<?php
/**
 * Classe Corrector pour l'évaluation des codes soumis
 * 
 * Cette classe compare la sortie du code de l'élève avec les résultats attendus.
 */

class Corrector {
    private $submission;
    private $referenceTests = [];
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
        
        // Créer un répertoire temporaire unique pour cette correction
        $this->tmp_dir = sys_get_temp_dir() . '/correction_' . uniqid() . '/';
        if (!file_exists($this->tmp_dir)) {
            mkdir($this->tmp_dir, 0755, true);
        }
        
        // Charger les tests de référence pour l'exercice
        $this->loadReferenceTests();
    }
    
    /**
     * Charger les tests de référence depuis la base de données
     */
    private function loadReferenceTests() {
        $db = db_connect(true); // Connexion en lecture seule
        
        $stmt = $db->prepare("
            SELECT test_number, arguments, expected_output
            FROM reference_tests
            WHERE exercise_id = ? AND language_id = ?
            ORDER BY test_number ASC
        ");
        
        $stmt->execute([
            $this->submission['exercise_id'],
            $this->submission['language_id']
        ]);
        
        $this->referenceTests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($this->referenceTests)) {
            throw new Exception("Aucun test de référence trouvé pour cet exercice");
        }
    }
    
    /**
     * Exécuter la correction complète
     * 
     * @return array Résultat de la correction avec score et détails
     */
    public function run() {
        try {
            // Copier le fichier soumis
            $sourcePath = $this->copySubmissionFile();
            
            // Préparer le fichier pour l'exécution selon le langage
            switch(strtolower($this->submission['language_name'])) {
                case 'c':
                    $executablePath = $this->compileC($sourcePath);
                    break;
                case 'python':
                    $executablePath = $sourcePath; // Python ne nécessite pas de compilation
                    break;
                default:
                    throw new Exception("Langage non supporté: " . $this->submission['language_name']);
            }
            
            // Exécuter tous les tests
            $this->runAllTests($executablePath);
            
            // Calculer le score
            $score = $this->calculateScore();
            
            return [
                'success' => true,
                'score' => $score,
                'details' => $this->results
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'score' => 0,
                'error' => $e->getMessage()
            ];
        } finally {
            // Nettoyer les fichiers temporaires
            $this->cleanup();
        }
    }
    
    /**
     * Copier le fichier de soumission vers un emplacement temporaire
     * 
     * @return string Chemin vers le fichier copié
     */
    private function copySubmissionFile() {
        $filePath = $this->submission['file_path'];
        if (!file_exists($filePath)) {
            throw new Exception("Fichier soumis introuvable: $filePath");
        }
        
        $newPath = $this->tmp_dir . basename($filePath);
        if (!copy($filePath, $newPath)) {
            throw new Exception("Impossible de copier le fichier soumis");
        }
        
        return $newPath;
    }
    
    /**
     * Compiler un programme C
     * 
     * @param string $sourcePath Chemin vers le fichier source
     * @return string Chemin vers l'exécutable généré
     */
    private function compileC($sourcePath) {
        $outputPath = $this->tmp_dir . 'program';
        
        $command = 'gcc -Wall -o ' . escapeshellarg($outputPath) . ' ' . escapeshellarg($sourcePath) . ' 2>&1';
        exec($command, $output, $returnVal);
        
        if ($returnVal !== 0) {
            throw new Exception("Erreur de compilation: " . implode("\n", $output));
        }
        
        return $outputPath;
    }
    
    /**
     * Exécuter tous les tests de référence
     * 
     * @param string $executablePath Chemin vers l'exécutable à tester
     */
    private function runAllTests($executablePath) {
        foreach ($this->referenceTests as $test) {
            $testNumber = $test['test_number'];
            $arguments = $test['arguments'];
            $expectedOutput = $test['expected_output'];
            
            try {
                $actualOutput = $this->executeTest($executablePath, $arguments);
                
                // Normaliser les sorties pour la comparaison (espaces, sauts de ligne)
                $normalizedExpected = $this->normalizeOutput($expectedOutput);
                $normalizedActual = $this->normalizeOutput($actualOutput);
                
                // Comparer les sorties
                $passed = ($normalizedExpected === $normalizedActual);
                
                $this->results[$testNumber] = [
                    'passed' => $passed,
                    'expected' => $expectedOutput,
                    'output' => $actualOutput
                ];
                
            } catch (Exception $e) {
                $this->results[$testNumber] = [
                    'passed' => false,
                    'expected' => $expectedOutput,
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    
    /**
     * Exécuter un test spécifique
     * 
     * @param string $executablePath Chemin vers l'exécutable
     * @param string $arguments Arguments de ligne de commande
     * @return string Sortie du programme
     */
    private function executeTest($executablePath, $arguments) {
        $timeout = 5; // 5 secondes maximum d'exécution
        
        switch (strtolower($this->submission['language_name'])) {
            case 'c':
                $command = "timeout $timeout " . escapeshellarg($executablePath) . " " . $arguments . " 2>&1";
                break;
            case 'python':
                $command = "timeout $timeout python3 " . escapeshellarg($executablePath) . " " . $arguments . " 2>&1";
                break;
            default:
                throw new Exception("Langage non supporté pour l'exécution");
        }
        
        $output = [];
        $returnVal = 0;
        
        exec($command, $output, $returnVal);
        
        if ($returnVal === 124 || $returnVal === 137) {
            throw new Exception("L'exécution a dépassé le délai maximum ($timeout secondes)");
        } elseif ($returnVal !== 0) {
            throw new Exception("Erreur lors de l'exécution (code $returnVal): " . implode("\n", $output));
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
        // Remplacer les fins de ligne Windows par Unix
        $output = str_replace("\r\n", "\n", $output);
        
        // Supprimer les espaces et tabulations en début et fin de ligne
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
     * Calculer le score basé sur les résultats des tests
     * 
     * @return float Score de 0 à 100
     */
    private function calculateScore() {
        if (empty($this->results)) {
            return 0;
        }
        
        $totalTests = count($this->results);
        $passedTests = 0;
        
        foreach ($this->results as $result) {
            if ($result['passed']) {
                $passedTests++;
            }
        }
        
        return round(($passedTests / $totalTests) * 100, 2);
    }
    
    /**
     * Nettoyer les fichiers temporaires
     */
    private function cleanup() {
        $this->deleteDirectory($this->tmp_dir);
    }
    
    /**
     * Supprimer un répertoire et son contenu récursivement
     * 
     * @param string $dir Chemin du répertoire à supprimer
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}
?>
