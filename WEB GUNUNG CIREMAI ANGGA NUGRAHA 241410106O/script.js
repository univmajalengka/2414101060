
        // --- 1. DARK MODE TOGGLE ---
        const themeToggle = document.getElementById('theme-toggle');
        const themeToggleMobile = document.getElementById('theme-toggle-mobile');
        const html = document.documentElement;

        function toggleTheme() {
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
        }

        // Check Local Storage saat load
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        }

        themeToggle.addEventListener('click', toggleTheme);
        themeToggleMobile.addEventListener('click', toggleTheme);

        // --- 2. MOBILE MENU ---
        const btnMenu = document.getElementById('mobile-menu-btn');
        const menu = document.getElementById('mobile-menu');

        btnMenu.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });

        // --- 3. HERO CAROUSEL ---
        const heroBg = document.getElementById('hero-bg');
        // Gambar placeholder pemandangan
        const images = [
            'img/gunung1.jpg', // Pemandangan Gunung
            'img/gunung2.jpeg', // Pantai
            'img/gunung3.jpeg'  // Sawah/Bali
        ];
        let currentImg = 0;

        function changeHeroImage() {
            heroBg.style.opacity = '0.5'; // Fade out sedikit
            setTimeout(() => {
                heroBg.style.backgroundImage = `url('${images[currentImg]}')`;
                heroBg.style.opacity = '1'; // Fade in
                currentImg = (currentImg + 1) % images.length;
            }, 500);
        }

        // Set awal
        heroBg.style.backgroundImage = `url('${images[0]}')`;
        setInterval(changeHeroImage, 5000); // Ganti setiap 5 detik

        // --- 4. RATING SYSTEM (LOCAL STORAGE) ---
        const ratingForm = document.getElementById('ratingForm');
        const reviewsList = document.getElementById('reviewsList');

        // Data awal dummy jika kosong
        const defaultReviews = [
            { id: 1, name: "Budi Santoso", rating: 5, comment: "Pengalaman luar biasa! Pemandunya ramah." },
            { id: 2, name: "Siti Aminah", rating: 4, comment: "Tempatnya indah, tapi agak macet di jalan menuju lokasi." }
        ];

        function getReviews() {
            const reviews = localStorage.getItem('wisataReviews');
            return reviews ? JSON.parse(reviews) : defaultReviews;
        }

        function saveReviews(reviews) {
            localStorage.setItem('wisataReviews', JSON.stringify(reviews));
            renderReviews();
        }

        function renderReviews() {
            const reviews = getReviews();
            reviewsList.innerHTML = '';

            reviews.forEach(review => {
                const stars = '⭐'.repeat(review.rating);
                const item = document.createElement('div');
                item.className = "bg-gray-50 dark:bg-slate-700 p-4 rounded-lg shadow-sm border border-gray-100 dark:border-slate-600 relative group";
                item.innerHTML = `
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="font-bold text-lg">${review.name}</h4>
                            <div class="text-yellow-400 text-sm mb-2">${stars}</div>
                            <p class="text-gray-600 dark:text-gray-300 text-sm">${review.comment}</p>
                        </div>
                        <button onclick="deleteReview(${review.id})" class="text-red-400 hover:text-red-600 opacity-20 group-hover:opacity-100 transition text-sm font-semibold" title="Hapus (Admin)">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                `;
                reviewsList.appendChild(item);
            });
        }

        // Handle Submit
        ratingForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const name = document.getElementById('reviewerName').value;
            const rating = parseInt(document.getElementById('reviewerRating').value);
            const comment = document.getElementById('reviewerComment').value;

            const newReview = {
                id: Date.now(),
                name,
                rating,
                comment
            };

            const reviews = getReviews();
            reviews.unshift(newReview); // Tambah ke atas
            saveReviews(reviews);
            ratingForm.reset();
            alert("Terima kasih atas ulasan Anda!");
        });

        // Handle Delete (Simulasi Admin)
        window.deleteReview = (id) => {
            // Simulasi proteksi admin sederhana
            const password = prompt("Masukkan password Admin untuk menghapus (Password: admin123):");
            if (password === 'admin123') {
                const reviews = getReviews();
                const updatedReviews = reviews.filter(r => r.id !== id);
                saveReviews(updatedReviews);
                alert("Review berhasil dihapus.");
            } else if (password !== null) {
                alert("Password salah! Akses ditolak.");
            }
        };

        // Render saat load
        renderReviews();