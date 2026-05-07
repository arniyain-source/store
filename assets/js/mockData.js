const mockProducts = [
    {
        id: 1,
        name: "Gold Chronograph Watch",
        price: 2499,
        oldPrice: 2999,
        img: "https://images.unsplash.com/photo-1523275335684-37898b6baf30",
        images: [
            "https://images.unsplash.com/photo-1523275335684-37898b6baf30",
            "https://images.unsplash.com/photo-1526045612212-70caf35c14df",
            "https://images.unsplash.com/photo-1524592094714-0f0654e20314"
        ],
        colors: 8,
        cat: "Watches",
        sku: "AH-W-01",
        tags: "gold luxury watch chronograph premium top selling boutique",
        desc: "A polished statement piece with a brushed gold case, sapphire-inspired dial detailing, and a precision quartz movement tuned for dependable everyday wear.",
        sizes: ["38mm", "42mm", "44mm"],
        finishes: [
            { name: "Gold", hex: "#d4af37" },
            { name: "Silver", hex: "#e5e4e2" },
            { name: "Noir", hex: "#1a1a1a" }
        ],
        features: [
            { icon: "fa-solid fa-shield-halved", label: "2 Year Warranty" },
            { icon: "fa-solid fa-droplet", label: "50m Water Resistant" },
            { icon: "fa-solid fa-microchip", label: "Precision Quartz" },
            { icon: "fa-solid fa-hand-holding-heart", label: "Hand Assembled" }
        ],
        rating: 4.8,
        reviews: 1240,
        topSelling: true,
        newArrival: true,
        boutiqueOnly: true,
        stockLabel: "In Stock. Ready to Ship.",
        deliveryDays: [3, 7],
        reelTitle: "The Artisanship",
        reelDescription: "A closer look at the polished case, weight, and dial detailing that make this watch a signature piece.",
        testimonials: [
            { name: "John D.", rating: 5, text: "The finish is rich, the case feels premium, and it looks even better in person." },
            { name: "Sarah K.", rating: 5, text: "A really elegant watch with a strong presence on the wrist without feeling bulky." },
            { name: "Michael R.", rating: 5, text: "It feels balanced, premium, and gift-worthy straight out of the box." },
            { name: "Elena V.", rating: 4, text: "The packaging and detailing made the whole purchase feel luxurious." }
        ]
    },
    {
        id: 2,
        name: "Platinum Ring",
        price: 1850,
        oldPrice: 2100,
        img: "https://images.unsplash.com/photo-1605100804763-247f67b3557e",
        images: [
            "https://images.unsplash.com/photo-1605100804763-247f67b3557e",
            "https://images.unsplash.com/photo-1605100804763-247f67b3557e",
            "https://images.unsplash.com/photo-1515562141207-7a88fb7ce338"
        ],
        colors: 3,
        cat: "Jewelry",
        sku: "AH-J-02",
        tags: "ring platinum diamond jewelry women premium",
        desc: "A polished platinum-tone ring designed with clean geometry, a brilliant central stone, and a balanced silhouette for evening wear and gifting.",
        sizes: ["6", "7", "8"],
        finishes: [
            { name: "Platinum", hex: "#d7dde1" },
            { name: "Rose Gold", hex: "#b76e79" },
            { name: "Onyx", hex: "#202124" }
        ],
        features: [
            { icon: "fa-regular fa-gem", label: "Brilliant Cut Stone" },
            { icon: "fa-solid fa-award", label: "Premium Finish" },
            { icon: "fa-solid fa-box-open", label: "Gift Ready Box" },
            { icon: "fa-solid fa-shield-halved", label: "Secure Fit Design" }
        ],
        rating: 4.8,
        reviews: 863,
        topSelling: true,
        newArrival: false,
        boutiqueOnly: true,
        stockLabel: "Only a few left in stock.",
        deliveryDays: [4, 8],
        reelTitle: "Light, Stone, Sparkle",
        reelDescription: "The ring catches light beautifully and feels elevated without being too loud.",
        testimonials: [
            { name: "Ava P.", rating: 5, text: "It looks refined and expensive, and the fit feels comfortable for daily wear." },
            { name: "Riya M.", rating: 5, text: "The shine is incredible and the setting looks very polished." },
            { name: "Chris L.", rating: 4, text: "Bought it as a gift and it landed really well." },
            { name: "Neha S.", rating: 5, text: "Elegant, clean, and easy to pair with other jewelry." }
        ]
    },
    {
        id: 3,
        name: "Noir Sunglasses",
        price: 350,
        oldPrice: 420,
        img: "https://images.unsplash.com/photo-1577803645773-f96470509666",
        images: [
            "https://images.unsplash.com/photo-1577803645773-f96470509666",
            "https://images.unsplash.com/photo-1577803645773-f96470509666",
            "https://images.unsplash.com/photo-1511499767150-a48a237f0083"
        ],
        colors: 9,
        cat: "Accessories",
        sku: "AH-A-03",
        tags: "sunglasses noir accessories eyewear black premium",
        desc: "Sharp lines, lightweight frames, and dark lenses give this pair a clean editorial look that fits both city and resort styling.",
        sizes: ["Standard"],
        finishes: [
            { name: "Noir", hex: "#161616" },
            { name: "Gold Rim", hex: "#caa44d" },
            { name: "Champagne", hex: "#d8c5a4" }
        ],
        features: [
            { icon: "fa-solid fa-sun", label: "UV Protected Lenses" },
            { icon: "fa-solid fa-feather", label: "Lightweight Frame" },
            { icon: "fa-solid fa-glasses", label: "Comfort Fit Arms" },
            { icon: "fa-solid fa-briefcase", label: "Hard Case Included" }
        ],
        rating: 4.7,
        reviews: 980,
        topSelling: true,
        newArrival: false,
        boutiqueOnly: false,
        stockLabel: "In Stock.",
        deliveryDays: [3, 6],
        reelTitle: "Editorial Eyewear",
        reelDescription: "Bold, clean, and easy to style with both streetwear and premium casual fits.",
        testimonials: [
            { name: "Miles T.", rating: 5, text: "They look crisp, feel light, and the case is genuinely nice." },
            { name: "Sana Q.", rating: 4, text: "The fit feels secure and the finish is premium for the price." },
            { name: "Lina B.", rating: 5, text: "The gold rim option looks especially elevated." },
            { name: "Raj P.", rating: 4, text: "Simple, sharp, and easy to wear every day." }
        ]
    },
    {
        id: 4,
        name: "Rose Oud Perfume",
        price: 899,
        oldPrice: 1200,
        img: "https://images.unsplash.com/photo-1594035910387-fea47794261f",
        images: [
            "https://images.unsplash.com/photo-1594035910387-fea47794261f",
            "https://images.unsplash.com/photo-1541643600914-78b084683601",
            "https://images.unsplash.com/photo-1615634260167-c8cdede054de"
        ],
        colors: 6,
        cat: "Perfumes",
        sku: "AH-P-04",
        tags: "perfume oud rose fragrance luxury scent boutique",
        desc: "A warm blend of rose, oud, amber, and spice designed to feel rich in the evening while staying wearable in the day.",
        sizes: ["50ml", "100ml", "150ml"],
        finishes: [
            { name: "Amber Glass", hex: "#8a5a44" },
            { name: "Black Bottle", hex: "#1c1c1c" },
            { name: "Gold Cap", hex: "#caa44d" }
        ],
        features: [
            { icon: "fa-solid fa-wind", label: "Long Lasting Trail" },
            { icon: "fa-solid fa-leaf", label: "Balanced Floral Notes" },
            { icon: "fa-solid fa-bottle-droplet", label: "Layering Friendly" },
            { icon: "fa-solid fa-gift", label: "Collector Bottle" }
        ],
        rating: 4.6,
        reviews: 730,
        topSelling: false,
        newArrival: false,
        boutiqueOnly: true,
        stockLabel: "In Stock.",
        deliveryDays: [3, 5],
        reelTitle: "The Signature Scent",
        reelDescription: "Warm rose and oud notes with a smooth dry down that feels evening-ready.",
        testimonials: [
            { name: "Nadia H.", rating: 5, text: "Rich without being too heavy, and it lasts beautifully." },
            { name: "Dev M.", rating: 4, text: "The bottle looks premium and the scent profile feels well blended." },
            { name: "Iris C.", rating: 5, text: "A very polished fragrance with a warm finish." },
            { name: "Aman G.", rating: 4, text: "Great gifting option and the presentation is strong." }
        ]
    },
    {
        id: 5,
        name: "Gold Chain Necklace",
        price: 900,
        oldPrice: 1099,
        img: "https://images.unsplash.com/photo-1549465220-1a8b9238cd48",
        images: [
            "https://images.unsplash.com/photo-1549465220-1a8b9238cd48",
            "https://images.unsplash.com/photo-1515562141207-7a88fb7ce338",
            "https://images.unsplash.com/photo-1617038220319-276d3cfab638"
        ],
        colors: 7,
        cat: "Jewelry",
        sku: "AH-J-05",
        tags: "gold chain necklace jewelry layering premium",
        desc: "A polished chain necklace with a soft shine and balanced weight, designed to sit cleanly on the neckline and layer well with rings or watches.",
        sizes: ["16in", "18in", "20in"],
        finishes: [
            { name: "Classic Gold", hex: "#d4af37" },
            { name: "Soft Matte", hex: "#b8892a" },
            { name: "Two Tone", hex: "#e5d28f" }
        ],
        features: [
            { icon: "fa-solid fa-link", label: "Balanced Link Weight" },
            { icon: "fa-solid fa-lock", label: "Secure Clasp" },
            { icon: "fa-solid fa-star", label: "Layering Friendly" },
            { icon: "fa-solid fa-sparkles", label: "Soft Shine Finish" }
        ],
        rating: 4.8,
        reviews: 560,
        topSelling: true,
        newArrival: false,
        boutiqueOnly: false,
        stockLabel: "In Stock.",
        deliveryDays: [3, 6],
        reelTitle: "Layered Gold",
        reelDescription: "A clean chain that adds shine without overwhelming the look.",
        testimonials: [
            { name: "Tina R.", rating: 5, text: "Light enough for regular wear but still feels luxe." },
            { name: "Karim J.", rating: 4, text: "The clasp is secure and the tone pairs well with other jewelry." },
            { name: "Ema F.", rating: 5, text: "Perfect layering piece and very giftable." },
            { name: "Yash K.", rating: 5, text: "Looks premium in person and catches the light nicely." }
        ]
    },
    {
        id: 6,
        name: "Obsidian Cufflinks",
        price: 250,
        oldPrice: 300,
        img: "https://images.unsplash.com/photo-1589758438368-0ad531db3366",
        images: [
            "https://images.unsplash.com/photo-1589758438368-0ad531db3366",
            "https://images.unsplash.com/photo-1589758438368-0ad531db3366",
            "https://images.unsplash.com/photo-1607083206968-13611e3d76db"
        ],
        colors: 4,
        cat: "Accessories",
        sku: "AH-A-06",
        tags: "cufflinks obsidian accessories formal men gift",
        desc: "Dark stone-inspired faces and clean metallic edges make these cufflinks a smart addition to tailored evening looks.",
        sizes: ["Standard"],
        finishes: [
            { name: "Obsidian", hex: "#111111" },
            { name: "Steel", hex: "#c8ccd3" },
            { name: "Gold Accent", hex: "#caa44d" }
        ],
        features: [
            { icon: "fa-solid fa-shirt", label: "Formal Wear Ready" },
            { icon: "fa-solid fa-circle-check", label: "Locking Back" },
            { icon: "fa-solid fa-box", label: "Presentation Box" },
            { icon: "fa-solid fa-user-tie", label: "Tailoring Essential" }
        ],
        rating: 4.7,
        reviews: 410,
        topSelling: false,
        newArrival: true,
        boutiqueOnly: false,
        stockLabel: "In Stock.",
        deliveryDays: [4, 7],
        reelTitle: "Formal Details",
        reelDescription: "Small styling details that instantly make tailored looks feel finished.",
        testimonials: [
            { name: "Victor S.", rating: 5, text: "They look polished and photograph really well for formal events." },
            { name: "Omar T.", rating: 4, text: "Good weight, strong finish, and easy to fasten." },
            { name: "Ishan P.", rating: 4, text: "Clean and subtle with just enough presence." },
            { name: "Leo C.", rating: 5, text: "Excellent gift option for anyone who wears suits often." }
        ]
    },
    {
        id: 7,
        name: "Noir Leather Wallet",
        price: 450,
        oldPrice: 550,
        img: "https://images.unsplash.com/photo-1627123424574-724758594e93",
        images: [
            "https://images.unsplash.com/photo-1627123424574-724758594e93",
            "https://images.unsplash.com/photo-1584917865442-de89df76afd3",
            "https://images.unsplash.com/photo-1548036328-c9fa89d128fa"
        ],
        colors: 2,
        cat: "Bags",
        sku: "AH-B-07",
        tags: "wallet leather noir bags compact gift",
        desc: "A slim everyday wallet cut from smooth black leather with a refined edge profile and a neatly organized interior.",
        sizes: ["Small", "Medium"],
        finishes: [
            { name: "Noir", hex: "#111111" },
            { name: "Espresso", hex: "#4c2f27" }
        ],
        features: [
            { icon: "fa-solid fa-wallet", label: "Slim Profile" },
            { icon: "fa-solid fa-shield-halved", label: "RFID Layer" },
            { icon: "fa-solid fa-credit-card", label: "Card Optimized" },
            { icon: "fa-solid fa-seedling", label: "Soft Grain Leather" }
        ],
        rating: 4.6,
        reviews: 615,
        topSelling: true,
        newArrival: false,
        boutiqueOnly: false,
        stockLabel: "In Stock.",
        deliveryDays: [3, 6],
        reelTitle: "Daily Carry, Elevated",
        reelDescription: "Compact and polished, with enough structure to feel premium in hand.",
        testimonials: [
            { name: "Sam R.", rating: 5, text: "Slim, sturdy, and well finished on the edges." },
            { name: "Kabir D.", rating: 4, text: "It feels more premium than most wallets in this range." },
            { name: "Mia A.", rating: 4, text: "Great gift item and the leather feels smooth." },
            { name: "Zane W.", rating: 5, text: "Minimal design but still has useful storage." }
        ]
    },
    {
        id: 8,
        name: "Heritage Weekender Bag",
        price: 3200,
        oldPrice: 3699,
        img: "https://images.unsplash.com/photo-1548036328-c9fa89d128fa",
        images: [
            "https://images.unsplash.com/photo-1548036328-c9fa89d128fa",
            "https://images.unsplash.com/photo-1548036328-c9fa89d128fa",
            "https://images.unsplash.com/photo-1512436991641-6745cdb1723f"
        ],
        colors: 4,
        cat: "Bags",
        sku: "AH-B-08",
        tags: "weekender bag leather travel heritage duffle boutique",
        desc: "A structured weekender bag with rich textures, reinforced handles, and a roomy interior built for short luxury getaways.",
        sizes: ["Medium", "Large"],
        finishes: [
            { name: "Walnut", hex: "#72503d" },
            { name: "Noir", hex: "#181818" },
            { name: "Sand", hex: "#c9b49a" }
        ],
        features: [
            { icon: "fa-solid fa-briefcase", label: "Cabin Friendly" },
            { icon: "fa-solid fa-layer-group", label: "Multi Pocket Interior" },
            { icon: "fa-solid fa-plane", label: "Travel Ready Build" },
            { icon: "fa-solid fa-hands-holding-circle", label: "Reinforced Handles" }
        ],
        rating: 4.7,
        reviews: 302,
        topSelling: false,
        newArrival: true,
        boutiqueOnly: true,
        stockLabel: "Made in limited batches.",
        deliveryDays: [5, 9],
        reelTitle: "Weekend Carry",
        reelDescription: "Built for short trips with a structured silhouette and a premium travel feel.",
        testimonials: [
            { name: "Ariel B.", rating: 5, text: "The structure, texture, and handle finish make it stand out instantly." },
            { name: "Rohan N.", rating: 4, text: "Perfect carry size for two- to three-day trips." },
            { name: "Marta C.", rating: 5, text: "Looks expensive, feels durable, and holds more than expected." },
            { name: "Ibrahim Q.", rating: 4, text: "A boutique-style bag with a great shape." }
        ]
    },
    {
        id: 9,
        name: "Emerald Signet Ring",
        price: 1100,
        oldPrice: 1399,
        img: "https://images.unsplash.com/photo-1617038220319-276d3cfab638",
        images: [
            "https://images.unsplash.com/photo-1617038220319-276d3cfab638",
            "https://images.unsplash.com/photo-1515562141207-7a88fb7ce338",
            "https://images.unsplash.com/photo-1605100804763-247f67b3557e"
        ],
        colors: 5,
        cat: "Jewelry",
        sku: "AH-J-09",
        tags: "emerald signet ring jewelry statement premium",
        desc: "A bold signet-inspired ring with a polished face and jewel-toned center, designed for dressed-up looks that still feel modern.",
        sizes: ["6", "7", "8"],
        finishes: [
            { name: "Emerald", hex: "#2c7a58" },
            { name: "Ruby", hex: "#8b1e3f" },
            { name: "Onyx", hex: "#1c1c1c" }
        ],
        features: [
            { icon: "fa-solid fa-ring", label: "Statement Face" },
            { icon: "fa-solid fa-palette", label: "Gem Inspired Finish" },
            { icon: "fa-solid fa-award", label: "Polished Edge Work" },
            { icon: "fa-solid fa-gift", label: "Collector Packaging" }
        ],
        rating: 4.7,
        reviews: 278,
        topSelling: false,
        newArrival: true,
        boutiqueOnly: true,
        stockLabel: "In Stock.",
        deliveryDays: [4, 8],
        reelTitle: "Modern Signet",
        reelDescription: "A more expressive ring silhouette with clean metalwork and jewel-toned accents.",
        testimonials: [
            { name: "Pooja V.", rating: 5, text: "Looks dramatic in the best way and still feels wearable." },
            { name: "Hugo A.", rating: 4, text: "The face is bold, but the fit stays comfortable." },
            { name: "Kira T.", rating: 5, text: "Very stylish ring with a premium feel." },
            { name: "Danish R.", rating: 4, text: "Strong detail work and a really polished finish." }
        ]
    },
    {
        id: 10,
        name: "Moonphase Skeleton Watch",
        price: 4200,
        oldPrice: 4799,
        img: "https://images.unsplash.com/photo-1606760227091-3dd870d97f1d",
        images: [
            "https://images.unsplash.com/photo-1606760227091-3dd870d97f1d",
            "https://images.unsplash.com/photo-1523275335684-37898b6baf30",
            "https://images.unsplash.com/photo-1526045612212-70caf35c14df"
        ],
        colors: 4,
        cat: "Watches",
        sku: "AH-W-10",
        tags: "moonphase skeleton watch boutique premium new arrival",
        desc: "A dressier watch with exposed movement-inspired detailing, a bold face, and a polished bracelet made for occasion dressing.",
        sizes: ["40mm", "42mm", "44mm"],
        finishes: [
            { name: "Steel", hex: "#cfd4db" },
            { name: "Gold", hex: "#d4af37" },
            { name: "Midnight", hex: "#14171d" }
        ],
        features: [
            { icon: "fa-solid fa-clock", label: "Skeleton Dial Detail" },
            { icon: "fa-solid fa-moon", label: "Moonphase Inspired Face" },
            { icon: "fa-solid fa-swatchbook", label: "Occasion Ready Finish" },
            { icon: "fa-solid fa-box", label: "Collector Case" }
        ],
        rating: 4.9,
        reviews: 184,
        topSelling: false,
        newArrival: true,
        boutiqueOnly: true,
        stockLabel: "Limited drop available now.",
        deliveryDays: [5, 9],
        reelTitle: "Mechanical Mood",
        reelDescription: "A dress watch with a more theatrical face and a boutique presentation.",
        testimonials: [
            { name: "Daniel P.", rating: 5, text: "This looks like a far more expensive watch than it is." },
            { name: "Ankit S.", rating: 5, text: "The dial is the star here, especially under evening light." },
            { name: "Julia R.", rating: 4, text: "Excellent gift option if you want something dramatic." },
            { name: "Nico L.", rating: 5, text: "The details and presentation are both strong." }
        ]
    },
    {
        id: 11,
        name: "Blanc Leather Clutch",
        price: 450,
        oldPrice: 590,
        img: "https://images.unsplash.com/photo-1584917865442-de89df76afd3",
        images: [
            "https://images.unsplash.com/photo-1584917865442-de89df76afd3",
            "https://images.unsplash.com/photo-1584917865442-de89df76afd3",
            "https://images.unsplash.com/photo-1548036328-c9fa89d128fa"
        ],
        colors: 3,
        cat: "Bags",
        sku: "AH-B-11",
        tags: "clutch leather bag blanc premium compact",
        desc: "A compact leather clutch with a clean envelope profile, light structure, and enough room for essentials during evening plans.",
        sizes: ["Small", "Medium"],
        finishes: [
            { name: "Blanc", hex: "#f0ece5" },
            { name: "Champagne", hex: "#d8c5a4" },
            { name: "Taupe", hex: "#8a7768" }
        ],
        features: [
            { icon: "fa-solid fa-envelope", label: "Envelope Shape" },
            { icon: "fa-solid fa-key", label: "Essentials Friendly" },
            { icon: "fa-solid fa-shield-halved", label: "Secure Magnetic Flap" },
            { icon: "fa-solid fa-sparkles", label: "Soft Premium Finish" }
        ],
        rating: 4.5,
        reviews: 228,
        topSelling: false,
        newArrival: true,
        boutiqueOnly: false,
        stockLabel: "In Stock.",
        deliveryDays: [3, 6],
        reelTitle: "Evening Carry",
        reelDescription: "Light, compact, and polished enough to elevate simple outfits.",
        testimonials: [
            { name: "Mina E.", rating: 5, text: "Great shape and the leather feels soft but structured." },
            { name: "Pallavi N.", rating: 4, text: "Compact in a good way and easy to style for dinners." },
            { name: "Sara J.", rating: 4, text: "The finish feels premium and the color options are lovely." },
            { name: "Khalid H.", rating: 5, text: "Really elegant piece for the price point." }
        ]
    }
];

function toPriceNumber(value) {
    if (typeof value === "number") return value;
    return Number(String(value).replace(/[^0-9.]/g, "")) || 0;
}

function formatPrice(value) {
    return `₹${toPriceNumber(value).toLocaleString("en-IN")}`;
}

function getProductById(id) {
    const numericId = Number(id);
    return mockProducts.find((product) => product.id === numericId) || null;
}

function getRelatedProducts(id, limit = 4) {
    return mockProducts.filter((product) => product.id !== Number(id)).slice(0, limit);
}
