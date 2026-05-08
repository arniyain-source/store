<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * AI Product Create - DesiVastra Admin
 * One-click product creation using AI extraction and analysis.
 */

$csrf = generateCSRF();
$flash = getFlash();

$db = getDB();
// Get categories for the select box
$stmt = $db->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC");
$categories = $stmt->fetchAll();

// ============================================
// HANDLE SAVE REQUEST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_ai_product') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        try {
            $name = sanitize($_POST['name'] ?? '');
            $sku = sanitize($_POST['sku'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $salePrice = (float)($_POST['sale_price'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $fabric = sanitize($_POST['fabric'] ?? '');
            $work = sanitize($_POST['work'] ?? '');
            $color = sanitize($_POST['color'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $metaTitle = sanitize($_POST['meta_title'] ?? '');
            $metaDesc = sanitize($_POST['meta_description'] ?? '');
            $status = ($_POST['status'] === 'active') ? 1 : 0;

            if (empty($name)) {
                throw new Exception('Product name is required.');
            }

            $slug = generateUniqueSlug($name, 'products');

            $stmt = $db->prepare("INSERT INTO products (
                name, slug, sku, price, old_price, stock, category_id, 
                fabric, work, colors, description, meta_title, meta_description, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $name, $slug, $sku, $price, ($salePrice > 0 ? $price : null), $stock, 
                ($categoryId > 0 ? $categoryId : null), $fabric, $work, $color, 
                $description, $metaTitle, $metaDesc, $status
            ]);

            $productId = $db->lastInsertId();

            logActivity('ai_create_product', 'product', $productId, ['name' => $name, 'sku' => $sku]);

            setFlash('success', 'AI Product "' . $name . '" created successfully.');
            redirect('products.php');

        } catch (Exception $e) {
            setFlash('error', 'Failed to save product: ' . $e->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Product Create - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .ai-container { max-width: 1100px; margin: 0 auto; }
        .ai-step { margin-bottom: 30px; }
        .ai-result-section { display: none; }
        .ai-result-section.visible { display: block; animation: fadeInUp 0.5s ease; }
        
        .upload-zone {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 40px;
            text-align: center;
            background: var(--bg-input);
            cursor: pointer;
            transition: var(--transition);
        }
        .upload-zone:hover { border-color: var(--gold-primary); background: rgba(184, 137, 42, 0.05); }
        .upload-zone i { font-size: 32px; color: var(--gold-primary); margin-bottom: 12px; }

        .analysis-loading {
            display: none;
            text-align: center;
            padding: 40px;
        }
        .whatsapp-preview-box {
            background: #0d1117;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 20px;
        }
        .whatsapp-preview {
            background: #075e54;
            color: #fff;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', Courier, monospace;
            white-space: pre-wrap;
            font-size: 13px;
            line-height: 1.5;
            border-left: 4px solid #128c7e;
            margin-bottom: 10px;
        }
        .regenerate-btn {
            background: none;
            border: none;
            color: var(--gold-primary);
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 0;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: 0.2s;
        }
        .regenerate-btn:hover { color: var(--gold-light); text-decoration: underline; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/layout.php'; ?>

    <div class="page-content">
        <div class="ai-container">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>AI Product Tools</span>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>AI Product Create</span>
            </div>

            <div class="page-header">
                <div>
                    <h1><i class="fas fa-magic" style="color: var(--gold-primary); margin-right: 8px;"></i> AI Product Create</h1>
                    <p class="subtitle">Extract product data from raw text using AI.</p>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo clean($flash['message']); ?>
                </div>
            <?php endif; ?>

            <!-- Step 1: Input -->
            <div class="card ai-step" id="step1">
                <div class="card-header">
                    <h3>Step 1: Raw Product Details</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Paste Messy Details (WhatsApp, Notes, etc.)</label>
                        <textarea id="aiRawText" class="form-control" rows="8" placeholder="Example:
New Saree Collection
Super hit design
SKU DV900
Price is 1850/-
Fabric: Georgette
Work: Sequence and Thread
Colors: Red, Blue, Pink..."></textarea>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Product Photos</label>
                            <div class="upload-zone">
                                <i class="fas fa-images"></i>
                                <p>Photos will be analyzed for color/work</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Product Video</label>
                            <div class="upload-zone">
                                <i class="fas fa-video"></i>
                                <p>AI detects pattern from video</p>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: right; margin-top: 10px;">
                        <button type="button" class="btn btn-primary btn-lg" onclick="analyzeWithAI()">
                            <i class="fas fa-brain"></i> Analyze & Extract Data
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div class="analysis-loading" id="aiLoading">
                <i class="fas fa-circle-notch fa-spin" style="font-size: 40px; color: var(--gold-primary); margin-bottom: 15px;"></i>
                <h3>AI is processing text and images...</h3>
                <p class="text-muted">Extracting attributes and generating marketing content.</p>
            </div>

            <!-- Step 2: Verification -->
            <form method="POST" id="aiResultForm" class="ai-result-section">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="save_ai_product">
                <input type="hidden" name="status" id="productStatus" value="draft">

                <div class="grid-2" style="grid-template-columns: 1.2fr 0.8fr;">
                    <div class="card">
                        <div class="card-header">
                            <h3>Step 2: Verify & Edit Details</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Product Name</label>
                                <input type="text" name="name" id="resName" class="form-control" required>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">SKU</label>
                                    <input type="text" name="sku" id="resSKU" class="form-control">
                                    <button type="button" class="regenerate-btn" onclick="regenerateSKU()"><i class="fas fa-sync-alt"></i> Regenerate SKU</button>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Price (₹)</label>
                                    <input type="number" name="price" id="resPrice" class="form-control" required>
                                </div>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">Sale Price (₹)</label>
                                    <input type="number" name="sale_price" id="resSalePrice" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Stock Qty</label>
                                    <input type="number" name="stock" id="resStock" class="form-control" value="10">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select name="category_id" id="resCategory" class="form-control">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>"><?php echo clean($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">Fabric</label>
                                    <input type="text" name="fabric" id="resFabric" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Work</label>
                                    <input type="text" name="work" id="resWork" class="form-control">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Color(s)</label>
                                <input type="text" name="color" id="resColor" class="form-control">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Full Description</label>
                                <textarea name="description" id="resDesc" class="form-control" rows="4"></textarea>
                            </div>

                            <div class="detail-divider"></div>
                            <h4 style="margin-bottom:15px; color:var(--gold-primary)">SEO Settings</h4>

                            <div class="form-group">
                                <label class="form-label">Meta Title</label>
                                <input type="text" name="meta_title" id="resMetaTitle" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Meta Description</label>
                                <textarea name="meta_description" id="resMetaDesc" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="sticky-side">
                        <div class="card whatsapp-preview-box">
                            <div class="card-header" style="padding:0 0 15px 0;">
                                <h3><i class="fab fa-whatsapp"></i> WhatsApp Share Preview</h3>
                            </div>
                            <div class="card-body" style="padding:0;">
                                <div class="whatsapp-preview" id="waPreview"></div>
                                <div style="font-size: 11px; color: var(--text-muted); display: flex; align-items: flex-start; gap: 8px; background: rgba(255,255,255,0.03); padding: 10px; border-radius: 4px;">
                                    <i class="fas fa-shield-alt" style="margin-top:2px"></i>
                                    <span>Security Rule: Product URLs and Website links are automatically removed from share text.</span>
                                </div>
                            </div>
                        </div>

                        <div class="card" style="margin-top:20px;">
                            <div class="card-header">
                                <h3>Actions</h3>
                            </div>
                            <div class="card-body">
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <button type="submit" class="btn btn-secondary full-width" onclick="document.getElementById('productStatus').value='draft'">
                                        <i class="fas fa-save"></i> Save as Draft
                                    </button>
                                    <button type="submit" class="btn btn-primary full-width" onclick="document.getElementById('productStatus').value='active'">
                                        <i class="fas fa-paper-plane"></i> Publish Product
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary" style="background:none; border:none;" onclick="location.reload()">
                                        <i class="fas fa-undo"></i> Start Over
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/**
 * Simulate AI Extraction from Raw Text
 */
function analyzeWithAI() {
    const rawText = document.getElementById('aiRawText').value;
    if (!rawText.trim()) {
        alert('Please paste some product details first.');
        return;
    }

    document.getElementById('aiLoading').style.display = 'block';
    document.getElementById('step1').style.display = 'none';

    // Simulate Network/AI processing delay
    setTimeout(() => {
        // MOCK EXTRACTION LOGIC
        const nameMatch = rawText.match(/(?:Name|Title|Product)[:\-\s]+(.*)/i);
        const priceMatch = rawText.match(/(?:Price|Rate|Amt)[:\-\s]+(\d+)/i);
        const skuMatch = rawText.match(/(?:SKU|Code)[:\-\s]+([A-Z0-9\-]+)/i);
        const fabricMatch = rawText.match(/(?:Fabric|Material)[:\-\s]+(.*)/i);
        const workMatch = rawText.match(/(?:Work|Design)[:\-\s]+(.*)/i);
        const colorMatch = rawText.match(/(?:Color|Colour)[:\-\s]+(.*)/i);

        document.getElementById('resName').value = nameMatch ? nameMatch[1].trim() : "Premium Designer Collection Saree";
        document.getElementById('resPrice').value = priceMatch ? priceMatch[1] : "1850";
        document.getElementById('resSKU').value = skuMatch ? skuMatch[1] : "DV-" + Math.floor(Math.random() * 9000 + 1000);
        document.getElementById('resFabric').value = fabricMatch ? fabricMatch[1].trim() : "Soft Silk / Georgette";
        document.getElementById('resWork').value = workMatch ? workMatch[1].trim() : "Hand Embroidery & Zari";
        document.getElementById('resColor').value = colorMatch ? colorMatch[1].trim() : "Multi-Color";
        document.getElementById('resDesc').value = rawText.substring(0, 300) + "...";

        // Auto generate SEO
        document.getElementById('resMetaTitle').value = "Buy " + document.getElementById('resName').value + " | DesiVastra";
        document.getElementById('resMetaDesc').value = "Premium quality product. " + document.getElementById('resWork').value + " on " + document.getElementById('resFabric').value + ". Shop at best prices.";

        document.getElementById('aiLoading').style.display = 'none';
        document.querySelector('.ai-result-section').classList.add('visible');
        
        updateWAPreview();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }, 2000);
}

/**
 * Regenerate SKU logic
 */
function regenerateSKU() {
    document.getElementById('resSKU').value = "DV-AI-" + Math.floor(Math.random() * 9000 + 1000);
    updateWAPreview();
}

/**
 * Dynamic WhatsApp Share Preview (No URLs)
 */
function updateWAPreview() {
    const name = document.getElementById('resName').value;
    const sku = document.getElementById('resSKU').value;
    const price = document.getElementById('resPrice').value;
    const salePrice = document.getElementById('resSalePrice').value;
    const fabric = document.getElementById('resFabric').value;
    const work = document.getElementById('resWork').value;
    const color = document.getElementById('resColor').value;
    
    const finalPrice = salePrice > 0 ? salePrice : price;

    const waText = `*Product Details*

*Saree :-* ${name}
*Fabric :-* ${fabric}
*Work :-* ${work}
*Color :-* ${color}
*SKU :-* ${sku}

*Price :-* ₹${finalPrice}/-

Ready Stock | Premium Collection`;

    document.getElementById('waPreview').innerText = waText;
}

// Event Listeners for real-time preview updates
const fields = ['resName', 'resSKU', 'resPrice', 'resSalePrice', 'resFabric', 'resWork', 'resColor'];
fields.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', updateWAPreview);
});

</script>

</body>
</html>