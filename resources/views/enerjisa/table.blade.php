@php($columns = collect($rows)->flatMap(fn($row) => array_keys($row))->unique()->values())
<div class="table-wrap"><table><thead><tr>@foreach($columns as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>
@foreach($rows as $row)<tr>@foreach($columns as $column)<td>{{ is_bool($row[$column] ?? null) ? (($row[$column] ?? false) ? 'Evet' : 'Hayır') : ($row[$column] ?? '—') }}</td>@endforeach</tr>@endforeach
</tbody></table></div>
