/**
 * DesiVastra Admin Panel - Common JavaScript
 */

// ============================================
// TOAST NOTIFICATIONS
// ============================================
function showToast(message, type = 'success', duration = 4000) {
    const container = document.getElementById('toastContainer') || createToastContainer();
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const toast = document.createElement('div');
    toast.className = `flash-message flash-${type}`;
    toast.style.cssText = 'position:relative;margin-bottom:8px;animation:fadeIn 0.3s ease;';
    toast.innerHTML = `
        <i class="fas ${icons[type] || icons.info}"></i>
        <span>${message}</span>
        <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;max-width:380px;';
    document.body.appendChild(container);
    return container;
}

// ============================================
// MODAL HELPERS
// ============================================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal-overlay.show');
        if (openModal) {
            openModal.classList.remove('show');
            document.body.style.overflow = '';
        }
    }
});

// ============================================
// CONFIRM DIALOG
// ============================================
function confirmAction(message) {
    return new Promise((resolve) => {
        resolve(window.confirm(message));
    });
}

// ============================================
// FORM HELPERS
// ============================================

// Auto-submit filter forms on dropdown change
document.addEventListener('change', function(e) {
    if (e.target.matches('.filter-auto-submit')) {
        e.target.closest('form').submit();
    }
});

// Toggle password visibility
function togglePasswordField(btn) {
    const input = btn.parentElement.querySelector('input');
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// ============================================
// IMAGE UPLOAD PREVIEW
// ============================================
function previewImage(input, previewElementId) {
    const preview = document.getElementById(previewElementId);
    if (!preview || !input.files || !input.files[0]) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}

function previewMultipleImages(input, containerId) {
    const container = document.getElementById(containerId);
    if (!container || !input.files) return;
    
    container.innerHTML = '';
    Array.from(input.files).forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const item = document.createElement('div');
            item.className = 'image-preview-item';
            item.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <button type="button" class="remove-img" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(item);
        };
        reader.readAsDataURL(file);
    });
}

// ============================================
// TAGS INPUT
// ============================================
function initTagsInput(container) {
    const input = container.querySelector('input');
    if (!input) return;
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const value = this.value.trim().replace(/,/g, '');
            if (value) {
                addTag(container, value);
                this.value = '';
            }
        } else if (e.key === 'Backspace' && !this.value) {
            const lastTag = container.querySelector('.tag:last-of-type');
            if (lastTag) lastTag.remove();
        }
    });
    
    container.addEventListener('click', function() {
        input.focus();
    });
}

function addTag(container, value) {
    // Check for duplicates
    const existingTags = container.querySelectorAll('.tag span:first-child');
    for (const tag of existingTags) {
        if (tag.textContent.toLowerCase() === value.toLowerCase()) return;
    }
    
    const tag = document.createElement('span');
    tag.className = 'tag';
    tag.innerHTML = `<span>${value}</span><span class="remove-tag" onclick="this.parentElement.remove()">&times;</span>`;
    container.insertBefore(tag, container.querySelector('input'));
}

// ============================================
// AJAX HELPER
// ============================================
async function apiRequest(url, method = 'GET', data = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Request failed');
        }
        
        return result;
    } catch (error) {
        showToast(error.message, 'error');
        throw error;
    }
}

// ============================================
// EXPORT CSV HELPER
// ============================================
function exportCSV(filename, data) {
    const BOM = '\uFEFF';
    const blob = new Blob([BOM + data], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
    URL.revokeObjectURL(link.href);
}

// ============================================
// DEBOUNCE
// ============================================
function debounce(func, wait = 300) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// ============================================
// FORMAT HELPERS
// ============================================
function formatCurrency(amount) {
    return '₹' + Number(amount).toLocaleString('en-IN');
}

function formatNumber(num) {
    if (num >= 100000) return (num / 100000).toFixed(1) + 'L';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
}

// ============================================
// INIT ON DOM READY
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Init all tags inputs
    document.querySelectorAll('.tags-input').forEach(initTagsInput);
    
    // Auto-hide flash messages
    document.querySelectorAll('.flash-message').forEach(msg => {
        setTimeout(() => {
            msg.style.opacity = '0';
            msg.style.transform = 'translateY(-10px)';
            msg.style.transition = 'all 0.3s ease';
            setTimeout(() => msg.remove(), 300);
        }, 5000);
    });

    // Dashboard sales chart
    const salesChartCanvas = document.getElementById('salesChart');
    if (salesChartCanvas) {
        const salesData = JSON.parse(salesChartCanvas.dataset.sales);
        const salesChart = new Chart(salesChartCanvas, {
            type: 'line',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'Sales',
                    data: salesData.revenue,
                    borderColor: '#d4a853',
                    backgroundColor: 'rgba(212, 168, 83, 0.2)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
