/**
 * Script principal pour le site Coursero
 */

document.addEventListener('DOMContentLoaded', function() {
    // Fonction pour afficher des messages temporaires
    function showTemporaryMessage(message, type, duration = 5000) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        
        document.body.insertBefore(alert, document.body.firstChild);
        
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, duration);
    }
    
    // Gestion des formulaires avec validation
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('invalid');
                } else {
                    field.classList.remove('invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showTemporaryMessage('Veuillez remplir tous les champs obligatoires.', 'error');
            }
        });
    });
    
    // Animation pour les messages d'alerte
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        // Ajouter une classe pour l'animation
        alert.classList.add('alert-animated');
        
        // Option pour fermer l'alerte
        const closeBtn = document.createElement('span');
        closeBtn.innerHTML = '&times;';
        closeBtn.className = 'alert-close';
        closeBtn.addEventListener('click', () => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
        
        alert.appendChild(closeBtn);
    });
});
