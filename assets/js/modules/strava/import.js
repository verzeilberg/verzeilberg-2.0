/* Strava import (admin/strava/import): runs the import via AJAX and shows the result. */
const config = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('importForm');
    const btn = document.getElementById('importBtn');
    const resultDiv = document.getElementById('importResult');
    const summaryDiv = document.getElementById('resultSummary');
    const detailsDiv = document.getElementById('resultDetails');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Bezig met importeren...';
        resultDiv.style.display = 'none';

        try {
            const response = await fetch(config.importUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();
            
            if (data.success) {
                summaryDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Succes!</strong> ${data.count} nieuwe activiteiten geïmporteerd.
                    </div>
                `;
                
                if (data.activities && data.activities.length > 0) {
                    let html = '<ul class="list-group">';
                    data.activities.forEach(a => {
                        html += `<li class="list-group-item">${escapeHtml(a.name)} (${a.distance.toFixed(2)} km)</li>`;
                    });
                    html += '</ul>';
                    detailsDiv.innerHTML = html;
                }
            } else {
                summaryDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Fout:</strong> ${escapeHtml(data.message || '')}
                    </div>
                `;
            }
            
            resultDiv.style.display = 'block';
        } catch (error) {
            summaryDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Fout:</strong> Er is een onverwachte fout opgetreden.
                </div>
            `;
            resultDiv.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download me-2"></i>Activiteiten ophalen en importeren';
        }
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
