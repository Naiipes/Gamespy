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
<div class="border p-4 mb-3 flex items-center">

<img src="${game.game.thumb}" width="80">

<div class="ml-4">
<h2 class="font-bold">${game.game.title}</h2>
<p>Target: $${game.target_price}</p>
</div>

</div>
`

})

document.getElementById("wishlist").innerHTML = html

})

</script>

</x-app-layout>