<x-app-layout>

<div class="max-w-4xl mx-auto p-6">

<h1 class="text-2xl font-bold mb-4">
Price Alerts
</h1>

<div id="notifications"></div>

</div>

<script>

fetch("/api/notifications")
.then(res => res.json())
.then(data => {

let html = ""

data.forEach(n => {

html += `
<div class="border p-4 mb-3">

<h2 class="font-bold">${n.game.title}</h2>

<p>Price dropped to $${n.price}</p>

</div>
`

})

document.getElementById("notifications").innerHTML = html

})

</script>

</x-app-layout>