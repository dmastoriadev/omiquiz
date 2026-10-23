@if ($dynamic ?? false)
    <div class="bingo-card" :class="!card.active ? 'bingo-card--closed' : ''" :data-bingo-card-slot="card.slot" :aria-label="'Card ' + card.slot + (card.active ? ', active' : ', closed on play ' + card.closed_on_draw)">
        <div class="bingo-card-caption"><span x-text="'Card ' + card.slot"></span><span x-text="card.active ? 'Active' : 'Closed · ' + card.closed_on_draw"></span></div>
        <template x-if="Array.isArray(card.cells) && card.cells.length === 25">
            <div>
                <div class="bingo-card-letters" aria-hidden="true"><span>B</span><span>I</span><span>N</span><span>G</span><span>O</span></div>
                <div class="bingo-card-cells" role="group" aria-label="5 by 5 Bingo numbers">
                    <template x-for="(cell, cellIndex) in card.cells" :key="cellIndex">
                        <span class="bingo-card-cell" :class="{ 'bingo-card-cell--free': cell === null, 'bingo-card-cell--hit': cell !== null && (card.matched_numbers || []).includes(cell) }" :aria-label="cell === null ? 'Free center' : String(cell) + ((card.matched_numbers || []).includes(cell) ? ', called' : '')" x-text="cell === null ? 'FREE' : cell"></span>
                    </template>
                </div>
            </div>
        </template>
        <template x-if="!Array.isArray(card.cells) || card.cells.length !== 25"><div class="bingo-legacy-number" :data-bingo-card-number="card.number" x-text="card.number"></div></template>
        <p class="bingo-card-status" x-text="card.active ? 'No called number' : 'Card eliminated'"></p>
    </div>
@else
    <div class="bingo-card {{ $card['active'] ? '' : 'bingo-card--closed' }}" data-bingo-card-slot="{{ $card['slot'] }}" aria-label="Card {{ $card['slot'] }}, {{ $card['active'] ? 'active' : 'closed on play '.$card['closed_on_draw'] }}">
        <div class="bingo-card-caption"><span>Card {{ $card['slot'] }}</span><span>{{ $card['active'] ? 'Active' : 'Closed · '.$card['closed_on_draw'] }}</span></div>
        @if (is_array($card['cells'] ?? null) && count($card['cells']) === 25)
            <div class="bingo-card-letters" aria-hidden="true"><span>B</span><span>I</span><span>N</span><span>G</span><span>O</span></div>
            <div class="bingo-card-cells" role="group" aria-label="5 by 5 Bingo numbers">
                @foreach ($card['cells'] as $cell)
                    @php($hit = $cell !== null && in_array($cell, $card['matched_numbers'] ?? [], true))
                    <span class="bingo-card-cell {{ $cell === null ? 'bingo-card-cell--free' : '' }} {{ $hit ? 'bingo-card-cell--hit' : '' }}" aria-label="{{ $cell === null ? 'Free center' : $cell.($hit ? ', called' : '') }}">{{ $cell === null ? 'FREE' : $cell }}</span>
                @endforeach
            </div>
        @else
            <div class="bingo-legacy-number" data-bingo-card-number="{{ $card['number'] }}">{{ $card['number'] }}</div>
        @endif
        <p class="bingo-card-status">{{ $card['active'] ? 'No called number' : 'Card eliminated' }}</p>
    </div>
@endif
