/**
 * Meesho-style Share Product System
 * Handles product media sharing to WhatsApp without URLs
 */

async function shareProduct(productId) {
    try {
        showToast("Generating share details...", "info");

        // 1. Fetch Product Details
        const response = await fetch(`api/products/index.php?action=get&id=${productId}`);
        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || "Failed to fetch product data");
        }

        const product = result.product;

        // 2. Construct WhatsApp Text (NO URLs)
        const shareText = `*Product Details*

*Name :-* ${product.name}
*SKU :-* ${product.sku || 'N/A'}
*Price :-* ₹${product.price}/-

${product.short_description || ''}

Ready Stock | Premium Collection`;

        const encodedText = encodeURIComponent(shareText);
        const imageUrl = product.main_image ? `${window.location.origin}/${product.main_image}` : null;

        // 3. Platform Specific Sharing
        if (navigator.share && /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            // MOBILE: Use Web Share API
            try {
                let shareData = {
                    title: product.name,
                    text: shareText
                };

                // Try to share file if image exists
                if (imageUrl) {
                    const imgRes = await fetch(imageUrl);
                    const blob = await imgRes.blob();
                    const file = new File([blob], `${product.sku || 'product'}.jpg`, { type: blob.type });
                    
                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        shareData.files = [file];
                    }
                }

                await navigator.share(shareData);
                showToast("Shared successfully!", "success");
            } catch (err) {
                // Fallback to simple WhatsApp link if file share fails
                window.open(`https://api.whatsapp.com/send?text=${encodedText}`, '_blank');
            }
        } else {
            // DESKTOP: Download Image + Open WhatsApp Web
            showToast("Downloading media & opening WhatsApp...", "info");

            if (imageUrl) {
                const link = document.createElement('a');
                link.href = imageUrl;
                link.download = `${product.sku || 'product'}.jpg`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            // Open WhatsApp Web
            setTimeout(() => {
                window.open(`https://web.whatsapp.com/send?text=${encodedText}`, '_blank');
            }, 1000);
            
            showToast("Ready to share!", "success");
        }

        // Log sharing activity
        logShareActivity(productId);

    } catch (error) {
        console.error("Share error:", error);
        showToast("Error generating share details.", "error");
    }
}

/**
 * Log Share Activity to Backend
 */
async function logShareActivity(productId) {
    try {
        const formData = new FormData();
        formData.append('action', 'log_share');
        formData.append('product_id', productId);
        
        await fetch('api/products/index.php', {
            method: 'POST',
            body: formData
        });
    } catch (e) {
        // Silent fail for logging
    }
}

/**
 * Global Toast Helper (Fallback if not defined)
 */
if (typeof showToast !== 'function') {
    window.showToast = function(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.position = 'fixed';
        toast.style.bottom = '80px';
        toast.style.left = '50%';
        toast.style.transform = 'translateX(-50%)';
        toast.style.background = '#333';
        toast.style.color = '#fff';
        toast.style.padding = '12px 24px';
        toast.style.borderRadius = '30px';
        toast.style.zIndex = '9999';
        toast.style.fontSize = '14px';
        toast.innerText = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    };
}