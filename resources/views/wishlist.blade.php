<x-app-layout>

<div class="max-w-4xl mx-auto p-6">

<h1 class="text-2xl font-bold mb-4">
My Wishlist
</h1>

<div id="wishlist"></div>

</div>

<script>

fetch("/wishlist")
.then(res => res.json())
.then(data => {

let html = ""

data.forEach(game => {

html += `
<div class="border p-4 mb-3 flex items-center justify-between">

<div class="flex items-center">
<img src="${game.game.thumb}" width="80">

<div class="ml-4">
<h2 class="font-bold">${game.game.title}</h2>
<p>Target: $${game.target_price}</p>
</div>
</div>

<div>
<button class="delete-btn bg-red-500 text-white px-3 py-1 rounded" data-game-id="${game.game_id}">Delete</button>
</div>

</div>
`

})

document.getElementById("wishlist").innerHTML = html

// 削除ボタンのイベント
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const gameId = this.getAttribute('data-game-id');
        if (confirm('Are you sure you want to delete this game from wishlist?')) {
            fetch(`/wishlist/game/${gameId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.message === 'Game removed from wishlist') {
                    location.reload(); // ページリロードで更新
                } else {
                    alert('Delete failed: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }
    });
});

})

</script>

</x-app-layout>