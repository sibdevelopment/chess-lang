@props([
    'columns' => 5,
    'rows' => 3,
])

@for($row = 0; $row < $rows; $row++)
    <tr class="ui-loading-row">
        @for($column = 0; $column < $columns; $column++)
            <td><div class="ui-loading-bar"></div></td>
        @endfor
    </tr>
@endfor
