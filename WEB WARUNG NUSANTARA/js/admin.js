        function confirmDelete(menuId, menuName) {
            document.getElementById('menuId').value = menuId;
            document.getElementById('menuName').textContent = menuName;
            document.getElementById('deleteModal').classList.add('show');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        }