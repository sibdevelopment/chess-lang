{{-- Single source of truth for chess board CSS+JS.
     Include in @push('styles') / @push('scripts') is wrong; use it as
     a partial right after each board view's <head> push and before its inline script. --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/oakmac/chessboardjs@v1.0.0/website/css/chessboard.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/oakmac/chessboardjs@v1.0.0/website/js/chessboard.js"></script>
<script src="https://unpkg.com/chess.js@0.13.4/chess.js"></script>
<script>
  // Lichess uses the cburnett piece set (which is the SAME wikipedia set
  // that ships with chessboard.js — chessboardjs.com just hosts them as PNG).
  // Serve from chessboard.js's GitHub via jsDelivr — guaranteed to exist.
  window.LICHESS_PIECE_THEME = 'https://cdn.jsdelivr.net/gh/oakmac/chessboardjs@v1.0.0/website/img/chesspieces/wikipedia/{piece}.png';
</script>
