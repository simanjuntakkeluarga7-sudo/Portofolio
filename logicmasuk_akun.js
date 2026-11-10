const password = document.getElementById("password");
            const konfirmasi = document.getElementById("konfirmasi_password");
            const pesan = document.getElementById("pesan");
            const daftar = document.getElementById("daftar");
            const form = document.getElementById("formDaftar");

            daftar.disabled = true;

            konfirmasi.addEventListener("input", function() {
                if (password.value === konfirmasi.value) {
                    pesan.textContent = "Password sama";
                    pesan.style.color = "green";
                    daftar.disabled = false;
                }
                
                else { 

                    // Jika password berbeda 
                    pesan.textContent = "Password berbeda";
                    pesan.style.color = "red"; daftar.disabled = true; 
                }
            });
            
            // Ketika tombol daftar ditekan
            form.addEventListener("submit", function(event) { 
                if (password.value !== konfirmasi.value) { 
                    // Mencegah masuk ke proses selanjutnya 
                    event.preventDefault();

                    // Menampilkan pesan
                    pesan.textContent = "Password berbeda";
                    pesan.style.color = "red";

                    // Menghapus isi konfirmasi password
                    konfirmasi.value = "";

                    // Mengunci tombol daftar
                    daftar.disabled = true; }

            }); 
                        