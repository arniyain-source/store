document.addEventListener('DOMContentLoaded', () => {
    const state = {
        products: [],
        categories: [],
        filters: {
            query: '',
            category: 'All',
            minPrice: 0,
            maxPrice: 10000,
            sortBy: 'popularity',
        },
        lazyLoadObserver: null,
    };

    // Fetch initial data from the backend
    async function fetchData() {
        try {
            const response = await fetch('/api/products.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            state.products = data.products;
            state.categories = data.categories;
            renderProducts();
            renderCategoryFilter();
        } catch (error) {
            console.error("Could not fetch products:", error);
            document.getElementById('product-grid').innerHTML = '<p>Error loading products.</p>';
        }
    }

    // Render the products in the grid
    function renderProducts() {
        const grid = document.getElementById('product-grid');
        const filtered = applyFilters();
        
        if (filtered.length === 0) {
            grid.innerHTML = '<p>No products match your filters.</p>';
            return;
        }
        
        grid.innerHTML = filtered.map(p => createProductCard(p)).join('');
        lazyLoadImages();
    }

    // Apply all filters and sorting
    function applyFilters() {
        let products = [...state.products];

        // Filter by search query
        if (state.filters.query) {
            products = products.filter(p => p.name.toLowerCase().includes(state.filters.query.toLowerCase()));
        }

        // Filter by category
        if (state.filters.category !== 'All') {
            products = products.filter(p => p.category_name === state.filters.category);
        }

        // Filter by price
        products = products.filter(p => p.price >= state.filters.minPrice && p.price <= state.filters.maxPrice);

        // Sort products
        products.sort((a, b) => {
            switch (state.filters.sortBy) {
                case 'price_asc': return a.price - b.price;
                case 'price_desc': return b.price - a.price;
                default: return b.reviews_count - a.reviews_count; // Popularity
            }
        });

        return products;
    }

    // Create HTML for a single product card
    function createProductCard(product) {
        const image = product.main_image || 'assets/images/placeholder.jpg';
        return `
            <div class="product-card">
                <a href="product.php?id=${product.id}">
                    <img data-src="${image}" src="assets/images/placeholder.jpg" alt="${product.name}" class="lazy-load">
                    <h3>${product.name}</h3>
                    <p class="price">$${product.price}</p>
                </a>
                <button class="quick-view-btn" data-product-id="${product.id}">Quick View</button>
            </div>
        `;
    }

    // Render the category filter options
    function renderCategoryFilter() {
        const container = document.getElementById('category-filter');
        const cats = ['All', ...state.categories.map(c => c.name)];
        container.innerHTML = cats.map(c => `<button data-category="${c}">${c}</button>`).join('');
        container.querySelector('button').classList.add('active');
    }

    // Set up lazy loading for images
    function lazyLoadImages() {
        if (state.lazyLoadObserver) {
            state.lazyLoadObserver.disconnect();
        }

        state.lazyLoadObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy-load');
                    observer.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img.lazy-load').forEach(img => state.lazyLoadObserver.observe(img));
    }

    // Event Listeners
    function setupEventListeners() {
        // Search
        document.getElementById('search-input').addEventListener('input', e => {
            state.filters.query = e.target.value;
            renderProducts();
        });

        // Category filter
        const categoryContainer = document.getElementById('category-filter');
        categoryContainer.addEventListener('click', e => {
            if (e.target.tagName === 'BUTTON') {
                state.filters.category = e.target.dataset.category;
                categoryContainer.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
                e.target.classList.add('active');
                renderProducts();
            }
        });
        
        // Price filter (assuming a range slider)
        const priceSlider = document.getElementById('price-range');
        priceSlider.addEventListener('input', e => {
            document.getElementById('price-value').textContent = e.target.value;
        });
        priceSlider.addEventListener('change', e => {
            state.filters.maxPrice = Number(e.target.value);
            renderProducts();
        });

        // Sort
        document.getElementById('sort-by').addEventListener('change', e => {
            state.filters.sortBy = e.target.value;
            renderProducts();
        });
        
        // Quick view
        document.getElementById('product-grid').addEventListener('click', e => {
            if (e.target.classList.contains('quick-view-btn')) {
                const productId = e.target.dataset.productId;
                const product = state.products.find(p => p.id == productId);
                showQuickView(product);
            }
        });
    }

    // Show the quick view modal
    function showQuickView(product) {
        const modal = document.getElementById('quick-view-modal');
        modal.querySelector('#qv-product-name').textContent = product.name;
        modal.querySelector('#qv-product-image').src = product.main_image || 'assets/images/placeholder.jpg';
        modal.querySelector('#qv-product-price').textContent = `$${product.price}`;
        modal.querySelector('#qv-product-description').textContent = product.description;
        modal.style.display = 'block';
    }

    // Close quick view
    document.querySelector('.close-btn').addEventListener('click', () => {
        document.getElementById('quick-view-modal').style.display = 'none';
    });

    // Initial setup
    fetchData();
    setupEventListeners();
});
