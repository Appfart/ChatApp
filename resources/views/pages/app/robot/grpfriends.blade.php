<ul>
    @foreach ($friends as $friend)
        <li>{{ $friend->name }}</li>
    @endforeach
</ul>
