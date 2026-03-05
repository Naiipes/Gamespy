<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{ asset('css/style.css?v=') . time() }}">
    <title>Home</title>
</head>

<body>
    <nav>
        <ul class="header">
            <li class="home"><a href="">Gamespy</a></li>
            <li class="search-item">
                <form>
                    <input class="search-input" type="search" placeholder="Search game">
                    <button class="search-btn" type="submit"><x-search-icon/></button>
                </form>
            </li>
            <li><a href="">Free</a></li>
            <li><a href="">Categories</a></li>
            <li class="login"><a href=""><x-user-icon/></a></li>
        </ul>
    </nav>


    <footer>
        <p>&copy; 2026 Gamespy. All rights reserved.</p>
    </footer>
</body>

</html>
