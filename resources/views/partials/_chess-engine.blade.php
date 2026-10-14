{{-- Self-contained chess board widget. No CDN, no jQuery, no external assets.
     Exposes: window.MiniChess.{ Board, parsePGN, parseFen, toFen, applySAN, sqToIdx, idxToSq, getPiece, pieceMoves } --}}
<style>
.lc-board {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  grid-template-rows: repeat(8, 1fr);
  max-width: 100%;
  height: auto !important;
  aspect-ratio: 1 / 1;
  border: 2px solid #404040;
  box-shadow: 0 4px 24px rgba(0,0,0,0.18);
  user-select: none;
  position: relative;
  background: #b58863;
}
.lc-sq {
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: "Segoe UI Symbol", "Apple Symbols", "Noto Sans Symbols2", "DejaVu Sans", "Arial Unicode MS", serif;
  line-height: 1;
  position: relative;
  transition: background-color .08s;
}
.lc-sq.light { background: #f0d9b5; }
.lc-sq.dark  { background: #b58863; }
.lc-sq.sel   { background: #cdd26a !important; }
.lc-sq.dot::after {
  content: '';
  position: absolute;
  width: 28%;
  height: 28%;
  border-radius: 50%;
  background: rgba(20,85,30,.5);
  pointer-events: none;
}
.lc-sq.cap::after {
  content: '';
  position: absolute;
  inset: 0;
  border: 4px solid rgba(20,85,30,.5);
  border-radius: 50%;
  pointer-events: none;
  box-sizing: border-box;
}
/* SVG-rendered chess pieces (Cburnett-style, Lichess set) */
.lc-piece     { width: 92%; height: 92%; display:flex; align-items:center; justify-content:center; pointer-events:none; }
.lc-piece svg { width: 100%; height: 100%; display:block; filter: drop-shadow(0 1px 1px rgba(0,0,0,.18)); }
.lc-coord {
  position: absolute;
  font-size: 9px;
  font-family: var(--font-ui);
  font-weight: 700;
  pointer-events: none;
}
.lc-coord-file { bottom: 2px; right: 4px; }
.lc-coord-rank { top: 2px; left: 4px; }
.lc-coord.on-light { color: #b58863; }
.lc-coord.on-dark  { color: #f0d9b5; }
.lc-overlay {
  position: absolute; inset: 0; pointer-events: none;
  width: 100%; height: 100%;
}
.lc-sq.hl-green  { box-shadow: inset 0 0 0 4px rgba(20,160,60,.65); }
.lc-sq.hl-red    { box-shadow: inset 0 0 0 4px rgba(220,40,40,.65); }
.lc-sq.hl-blue   { box-shadow: inset 0 0 0 4px rgba(50,120,220,.65); }
.lc-sq.hl-yellow { box-shadow: inset 0 0 0 4px rgba(220,180,30,.65); }

/* Position editor */
.pe-wrap        { display:flex; gap:14px; align-items:flex-start; flex-wrap:wrap; }
.pe-side        { display:flex; flex-direction:column; gap:10px; min-width:140px; }
.pe-palette     { display:grid; grid-template-columns:repeat(2, 1fr); gap:6px; padding:8px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; }
.pe-palette-row { display:flex; gap:6px; }
.pe-pc          { width:38px; height:38px; display:flex; align-items:center; justify-content:center; background:#fff; border:2px solid #e2e8f0; border-radius:6px; cursor:pointer; user-select:none; padding:2px; }
.pe-pc svg      { width:100%; height:100%; display:block; }
.pe-pc.sel      { border-color:#2563eb; background:#dbeafe; box-shadow:0 0 0 2px rgba(37,99,235,.3); }
.pe-pc.eraser   { font-size:14px; font-weight:600; color:#475569; font-family:var(--font-ui); padding:6px 8px; }
.pe-opts        { display:flex; flex-direction:column; gap:6px; font-size:12px; color:#475569; }
.pe-opts label  { display:flex; align-items:center; gap:6px; cursor:pointer; }
.pe-fen-box     { font-family:ui-monospace, monospace; font-size:11px; padding:6px 8px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; word-break:break-all; }
</style>

<script>
(function () {
  const FILES = 'abcdefgh';

  function sqToIdx(sq) { return [parseInt(sq[1], 10) - 1, sq.charCodeAt(0) - 97]; }
  function idxToSq(rank, file) { return FILES[file] + (rank + 1); }
  function getPiece(board, rank, file) { return board[7 - rank][file]; }
  function setPiece(board, rank, file, piece) { board[7 - rank][file] = piece; }

  function parseFen(fen) {
    const parts = (fen || 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1').split(' ');
    const board = [];
    for (const row of parts[0].split('/')) {
      const r = [];
      for (const ch of row) {
        if (/\d/.test(ch)) for (let i = 0; i < parseInt(ch, 10); i++) r.push(null);
        else r.push(ch);
      }
      board.push(r);
    }
    return {
      board: board,
      turn: parts[1] || 'w',
      castling: parts[2] || '-',
      ep: parts[3] || '-',
      halfMove: parseInt(parts[4], 10) || 0,
      fullMove: parseInt(parts[5], 10) || 1
    };
  }

  function toFen(state) {
    const rows = [];
    for (const row of state.board) {
      let s = '', empty = 0;
      for (const cell of row) {
        if (cell === null) empty++;
        else { if (empty > 0) { s += empty; empty = 0; } s += cell; }
      }
      if (empty > 0) s += empty;
      rows.push(s);
    }
    return rows.join('/') + ' ' + state.turn + ' ' + state.castling + ' ' + state.ep + ' ' + state.halfMove + ' ' + state.fullMove;
  }

  function pieceMoves(state, rank, file) {
    const piece = getPiece(state.board, rank, file);
    if (!piece) return [];
    const isWhite = piece === piece.toUpperCase();
    const type = piece.toLowerCase();
    const moves = [];

    if (type === 'p') {
      const dir = isWhite ? 1 : -1;
      const startRank = isWhite ? 1 : 6;
      const oneAhead = rank + dir;
      if (oneAhead >= 0 && oneAhead <= 7 && !getPiece(state.board, oneAhead, file)) {
        moves.push([oneAhead, file]);
        if (rank === startRank && !getPiece(state.board, rank + 2 * dir, file)) {
          moves.push([rank + 2 * dir, file]);
        }
      }
      for (const df of [-1, 1]) {
        const tf = file + df;
        if (tf < 0 || tf > 7 || oneAhead < 0 || oneAhead > 7) continue;
        const target = getPiece(state.board, oneAhead, tf);
        if (target && (target === target.toUpperCase()) !== isWhite) {
          moves.push([oneAhead, tf]);
        }
        if (state.ep !== '-') {
          const ep = sqToIdx(state.ep);
          if (ep[0] === oneAhead && ep[1] === tf) moves.push([oneAhead, tf]);
        }
      }
    } else if (type === 'n') {
      const deltas = [[1,2],[2,1],[-1,2],[-2,1],[1,-2],[2,-1],[-1,-2],[-2,-1]];
      for (const [dr, df] of deltas) {
        const tr = rank + dr, tf = file + df;
        if (tr < 0 || tr > 7 || tf < 0 || tf > 7) continue;
        const t = getPiece(state.board, tr, tf);
        if (!t || (t === t.toUpperCase()) !== isWhite) moves.push([tr, tf]);
      }
    } else if (type === 'k') {
      const deltas = [[0,1],[0,-1],[1,0],[-1,0],[1,1],[1,-1],[-1,1],[-1,-1]];
      for (const [dr, df] of deltas) {
        const tr = rank + dr, tf = file + df;
        if (tr < 0 || tr > 7 || tf < 0 || tf > 7) continue;
        const t = getPiece(state.board, tr, tf);
        if (!t || (t === t.toUpperCase()) !== isWhite) moves.push([tr, tf]);
      }
    } else {
      const dirs = type === 'b' ? [[1,1],[1,-1],[-1,1],[-1,-1]]
                 : type === 'r' ? [[0,1],[0,-1],[1,0],[-1,0]]
                 : [[0,1],[0,-1],[1,0],[-1,0],[1,1],[1,-1],[-1,1],[-1,-1]];
      for (const [dr, df] of dirs) {
        let tr = rank, tf = file;
        while (true) {
          tr += dr; tf += df;
          if (tr < 0 || tr > 7 || tf < 0 || tf > 7) break;
          const t = getPiece(state.board, tr, tf);
          if (!t) moves.push([tr, tf]);
          else { if ((t === t.toUpperCase()) !== isWhite) moves.push([tr, tf]); break; }
        }
      }
    }
    return moves;
  }

  function applySAN(state, sanRaw) {
    let san = String(sanRaw).replace(/[+#!?]/g, '').trim();
    if (!san) return false;
    const isWhite = state.turn === 'w';

    if (san === 'O-O' || san === 'O-O-O' || san === '0-0' || san === '0-0-0') {
      const isKingside = san === 'O-O' || san === '0-0';
      const rank = isWhite ? 0 : 7;
      const newKingFile = isKingside ? 6 : 2;
      const oldRookFile = isKingside ? 7 : 0;
      const newRookFile = isKingside ? 5 : 3;
      setPiece(state.board, rank, 4, null);
      setPiece(state.board, rank, newKingFile, isWhite ? 'K' : 'k');
      setPiece(state.board, rank, oldRookFile, null);
      setPiece(state.board, rank, newRookFile, isWhite ? 'R' : 'r');
      state.castling = state.castling.replace(isWhite ? /[KQ]/g : /[kq]/g, '') || '-';
      state.ep = '-';
      state.turn = isWhite ? 'b' : 'w';
      if (!isWhite) state.fullMove++;
      state.halfMove++;
      return true;
    }

    let promotion = null;
    const promMatch = san.match(/=([QRBN])/i);
    if (promMatch) { promotion = promMatch[1].toUpperCase(); san = san.replace(/=[QRBNqrbn]/, ''); }

    const m = san.match(/^([KQRBN])?([a-h])?([1-8])?x?([a-h][1-8])$/);
    if (!m) return false;
    const pieceLetter   = m[1] || 'P';
    const fromFileLetter = m[2];
    const fromRankLetter = m[3];
    const target         = m[4];
    const [tr, tf] = sqToIdx(target);
    const sourcePiece = isWhite ? pieceLetter : pieceLetter.toLowerCase();

    let foundSrc = null;
    for (let r = 0; r < 8 && !foundSrc; r++) {
      for (let f = 0; f < 8 && !foundSrc; f++) {
        if (getPiece(state.board, r, f) !== sourcePiece) continue;
        if (fromFileLetter && f !== fromFileLetter.charCodeAt(0) - 97) continue;
        if (fromRankLetter && r !== parseInt(fromRankLetter, 10) - 1) continue;
        const moves = pieceMoves(state, r, f);
        if (moves.some(mm => mm[0] === tr && mm[1] === tf)) foundSrc = [r, f];
      }
    }
    if (!foundSrc) return false;

    const [sr, sf] = foundSrc;
    const movingPiece = getPiece(state.board, sr, sf);
    const wasCapture = !!getPiece(state.board, tr, tf);

    if (movingPiece.toLowerCase() === 'p' && state.ep !== '-' && target === state.ep) {
      const epDir = isWhite ? -1 : 1;
      setPiece(state.board, tr + epDir, tf, null);
    }

    setPiece(state.board, sr, sf, null);
    setPiece(state.board, tr, tf, promotion ? (isWhite ? promotion : promotion.toLowerCase()) : movingPiece);

    if (movingPiece.toLowerCase() === 'p' && Math.abs(tr - sr) === 2) {
      state.ep = idxToSq((sr + tr) / 2, sf);
    } else {
      state.ep = '-';
    }

    let cast = state.castling;
    if (movingPiece === 'K') cast = cast.replace(/[KQ]/g, '');
    if (movingPiece === 'k') cast = cast.replace(/[kq]/g, '');
    if (movingPiece === 'R') {
      if (sr === 0 && sf === 0) cast = cast.replace('Q', '');
      if (sr === 0 && sf === 7) cast = cast.replace('K', '');
    }
    if (movingPiece === 'r') {
      if (sr === 7 && sf === 0) cast = cast.replace('q', '');
      if (sr === 7 && sf === 7) cast = cast.replace('k', '');
    }
    if (tr === 0 && tf === 0) cast = cast.replace('Q', '');
    if (tr === 0 && tf === 7) cast = cast.replace('K', '');
    if (tr === 7 && tf === 0) cast = cast.replace('q', '');
    if (tr === 7 && tf === 7) cast = cast.replace('k', '');
    state.castling = cast || '-';

    state.turn = isWhite ? 'b' : 'w';
    if (!isWhite) state.fullMove++;
    state.halfMove = (movingPiece.toLowerCase() === 'p' || wasCapture) ? 0 : state.halfMove + 1;

    return true;
  }

  function cloneState(s) {
    return {
      board: s.board.map(row => row.slice()),
      turn: s.turn,
      castling: s.castling,
      ep: s.ep,
      halfMove: s.halfMove,
      fullMove: s.fullMove
    };
  }

  // Tokenize a PGN move-text body into structured tokens.
  // Tokens: {type:'san', value}, {type:'comment', value}, {type:'open'}, {type:'close'}
  // Move numbers (1.), NAGs ($5), and PGN result tokens are skipped.
  function tokenizePGNBody(body) {
    const tokens = [];
    let i = 0;
    while (i < body.length) {
      const ch = body[i];
      if (/\s/.test(ch)) { i++; continue; }
      if (ch === '{') {
        const end = body.indexOf('}', i);
        if (end < 0) break;
        tokens.push({ type: 'comment', value: body.slice(i + 1, end).trim() });
        i = end + 1;
        continue;
      }
      if (ch === '(') { tokens.push({ type: 'open' }); i++; continue; }
      if (ch === ')') { tokens.push({ type: 'close' }); i++; continue; }
      // Move number "12." or "12..." — skip
      const numMatch = body.slice(i).match(/^\d+\.+/);
      if (numMatch) { i += numMatch[0].length; continue; }
      // NAG "$5" — skip
      const nagMatch = body.slice(i).match(/^\$\d+/);
      if (nagMatch) { i += nagMatch[0].length; continue; }
      // PGN result — skip
      const resMatch = body.slice(i).match(/^(1-0|0-1|1\/2-1\/2|\*)\b/);
      if (resMatch) { i += resMatch[0].length; continue; }
      // SAN
      const sanMatch = body.slice(i).match(/^[a-zA-Z0O][a-zA-Z0-9+#=!?\-O]*/);
      if (sanMatch) {
        tokens.push({ type: 'san', value: sanMatch[0] });
        i += sanMatch[0].length;
        continue;
      }
      i++;
    }
    return tokens;
  }

  const NAG_TO_SYMBOL = {
    '$1': '!',
    '$2': '?',
    '$3': '!!',
    '$4': '??',
    '$5': '!?',
    '$6': '?!',
  };

  // Parse a single PGN game: extract headers, build mainLine + nested variations.
  // Returns {name, startFen, annotation, mainLine, headers, variations}
  // mainLine is an array of {san, fen, moveNumber, side, label, comment, variations}.
  // Each variations[k] is an array shaped like a mainLine.
  function parsePGNGame(text) {
    const headers = {};
    const headerRe = /\[(\w+)\s+"([^"]*)"\]/g;
    let m;
    while ((m = headerRe.exec(text)) !== null) {
      headers[m[1].toLowerCase()] = m[2];
    }
    const startFen = headers.fen || 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
    const rawName = headers.chaptername || headers.event || 'Position';
    const annotation = headers.initialcomment || '';

    // Strip headers from body
    let body = text.replace(/\[[^\]]*\]/g, ' ');

    const tokens = tokenizePGNBody(body);

    const startState = parseFen(startFen);
    const root = { state: cloneState(startState), line: [], parent: null, parentIdx: -1 };
    const stack = [root];
    let pendingComment = '';
    let firstComment = '';

    for (const tok of tokens) {
      const top = stack[stack.length - 1];
      if (tok.type === 'comment') {
        if (top.line.length > 0) {
          const last = top.line[top.line.length - 1];
          last.comment = (last.comment ? last.comment + ' ' : '') + tok.value;
        } else if (stack.length === 1 && root.line.length === 0) {
          firstComment = (firstComment ? firstComment + ' ' : '') + tok.value;
        } else {
          pendingComment = (pendingComment ? pendingComment + ' ' : '') + tok.value;
        }
        continue;
      }
      if (tok.type === 'nag') {
        if (top.line.length > 0) {
          const last = top.line[top.line.length - 1];
          last.nags = last.nags || [];
          last.nags.push(tok.value);
          if (!last.nag && NAG_TO_SYMBOL[tok.value]) last.nag = NAG_TO_SYMBOL[tok.value];
        }
        continue;
      }
      if (tok.type === 'open') {
        // A variation diverges from BEFORE the last move played in this line.
        const last = top.line[top.line.length - 1];
        if (!last) {
          // Can't start a variation with no prior move; treat as no-op
          stack.push({ state: cloneState(top.state), line: [], parent: null, parentIdx: -1, ghost: true });
          continue;
        }
        stack.push({
          state: cloneState(last.preState),
          line: [],
          parent: top.line,
          parentIdx: top.line.length - 1,
        });
        continue;
      }
      if (tok.type === 'close') {
        const closing = stack.pop();
        if (closing && closing.parent && closing.parentIdx >= 0 && !closing.ghost) {
          const parentMove = closing.parent[closing.parentIdx];
          parentMove.variations = parentMove.variations || [];
          parentMove.variations.push(closing.line);
        }
        continue;
      }
      if (tok.type === 'san') {
        const before = cloneState(top.state);
        if (applySAN(top.state, tok.value)) {
          const sideJustMoved = top.state.turn === 'w' ? 'b' : 'w';
          const moveNumber = sideJustMoved === 'w' ? top.state.fullMove : top.state.fullMove - 1;
          const label = (sideJustMoved === 'w' ? moveNumber + '. ' : moveNumber + '... ') + tok.value;
          top.line.push({
            san: tok.value,
            fen: toFen(top.state),
            preState: before,
            moveNumber: moveNumber,
            side: sideJustMoved,
            label: label,
            comment: pendingComment || '',
            nag: '',
            nags: [],
            variations: [],
          });
          pendingComment = '';
        }
      }
    }

    // Strip the bulky preState snapshots before returning
    function strip(line) {
      line.forEach(node => {
        delete node.preState;
        if (node.variations && node.variations.length) {
          node.variations.forEach(strip);
        }
      });
    }
    strip(root.line);

    return {
      name: rawName,
      startFen: startFen,
      annotation: annotation || firstComment,
      mainLine: root.line,
      headers: headers,
    };
  }

  // Parse a multi-game PGN file (Lichess Study export style).
  // Each [Event "..."] header begins a new game. Returns array of game objects.
  // If the input has no [Event] separators, returns a single-element array.
  function parsePGNFile(pgn) {
    const text = String(pgn || '').trim();
    if (!text) return [];
    // Split before each [Event header so the [Event line is included with its block.
    const blocks = text.split(/(?=^\[Event\s)/m).map(b => b.trim()).filter(Boolean);
    if (blocks.length === 0) {
      return [parsePGNGame(text)];
    }
    return blocks.map(b => parsePGNGame(b));
  }

  function parsePGN(pgn) {
    let body = String(pgn || '');
    // Strip header tags and PGN result, but KEEP {comments} so we can attach them.
    body = body.replace(/\[[^\]]*\]/g, ' ');
    // Strip nested variations (two passes covers most one-level nesting)
    body = body.replace(/\([^()]*\)/g, ' ');
    body = body.replace(/\([^()]*\)/g, ' ');
    body = body.replace(/(1-0|0-1|1\/2-1\/2|\*)\s*$/g, ' ');

    const positions = [];
    const state = parseFen('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');
    positions.push({ fen: toFen(state), move: null, label: 'Starting position', comment: '', moveNumber: 0, side: null });

    let i = 0;
    let pendingComment = '';
    while (i < body.length) {
      const ch = body[i];
      if (ch === '{') {
        const end = body.indexOf('}', i);
        if (end < 0) break;
        const c = body.slice(i + 1, end).trim();
        if (positions.length > 1) {
          // attach to most recent move
          const last = positions[positions.length - 1];
          last.comment = (last.comment ? last.comment + ' ' : '') + c;
        } else {
          pendingComment = (pendingComment ? pendingComment + ' ' : '') + c;
        }
        i = end + 1;
        continue;
      }
      if (/\s/.test(ch)) { i++; continue; }
      // Move number "12." or "12..." — skip
      const numMatch = body.slice(i).match(/^\d+\.+/);
      if (numMatch) { i += numMatch[0].length; continue; }
      // NAG "$5" — skip
      const nagMatch = body.slice(i).match(/^\$\d+/);
      if (nagMatch) { i += nagMatch[0].length; continue; }
      // Try a SAN token
      const sanMatch = body.slice(i).match(/^[a-zA-Z0O][a-zA-Z0-9+#=!?\-O]*/);
      if (sanMatch) {
        const san = sanMatch[0];
        i += san.length;
        if (applySAN(state, san)) {
          const sideJustMoved = state.turn === 'w' ? 'b' : 'w';   // turn already flipped
          const moveNumber = sideJustMoved === 'w' ? state.fullMove : state.fullMove - 1;
          let label = (sideJustMoved === 'w' ? moveNumber + '. ' : moveNumber + '... ') + san;
          positions.push({
            fen: toFen(state),
            move: san,
            label: label,
            comment: pendingComment || '',
            moveNumber: moveNumber,
            side: sideJustMoved,
          });
          pendingComment = '';
        }
        continue;
      }
      i++;
    }
    if (pendingComment && positions.length > 0) {
      positions[0].comment = pendingComment;
    }
    return positions;
  }

  // Cburnett SVG piece set — same as Lichess. Public domain.
  // 45x45 viewBox, white = #fff fill / black stroke, black = #000 fill / black stroke.
  const PIECES = {
    'K': '<svg viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22.5 11.63V6" stroke-linejoin="miter"/><path d="M22.5 25s4.5-7.5 3-10.5c0 0-1-2.5-3-2.5s-3 2.5-3 2.5c-1.5 3 3 10.5 3 10.5" fill="#fff" stroke-linecap="butt" stroke-linejoin="miter"/><path d="M11.5 37c5.5 3.5 15.5 3.5 21 0v-7s9-4.5 6-10.5c-4-6.5-13.5-3.5-16 4V27v-3.5c-2.5-7.5-12-10.5-16-4-3 6 6 10.5 6 10.5v7z" fill="#fff"/><path d="M20 8h5" stroke-linejoin="miter"/><path d="M32 29.5s8.5-4 6.03-9.65C34.15 14 25 18 22.5 24.5l.01 2.1-.01-2.1C20 18 9.906 14 6.997 19.85c-2.497 5.65 4.853 9 4.853 9M11.5 30c5.5-3 15.5-3 21 0m-21 3.5c5.5-3 15.5-3 21 0m-21 3.5c5.5-3 15.5-3 21 0"/></g></svg>',
    'Q': '<svg viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg"><g fill="#fff" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 26c8.5-1.5 21-1.5 27 0l2.5-12.5L31 25l-.3-14.1-5.2 13.6-3-14.5-3 14.5-5.2-13.6L14 25 6.5 13.5 9 26z" stroke-linecap="butt"/><path d="M9 26c0 2 1.5 2 2.5 4 1 1.5 1 1 .5 3.5-1.5 1-1 2.5-1 2.5-1.5 1.5.5 2.5.5 2.5 6.5 1 16.5 1 23 0 0 0 1.5-1-.5-2.5 0 0 .5-1.5-1-2.5-.5-2.5-.5-2 .5-3.5 1-2 2.5-2 2.5-4-8.5-1.5-18.5-1.5-27 0z" stroke-linecap="butt"/><path d="M11.5 30c3.5-1 18.5-1 22 0M12 33.5c6-1 15-1 21 0" fill="none"/><circle cx="6" cy="12" r="2"/><circle cx="14" cy="9" r="2"/><circle cx="22.5" cy="8" r="2"/><circle cx="31" cy="9" r="2"/><circle cx="39" cy="12" r="2"/></g></svg>',
    'R': '<svg viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg"><g fill="#fff" fill-rule="evenodd" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 39h27v-3H9v3zM12 36v-4h21v4H12zM11 14V9h4v2h5V9h5v2h5V9h4v5" stroke-linecap="butt"/><path d="M34 14l-3 3H14l-3-3"/><path d="M31 17v12.5H14V17" stroke-linecap="butt" stroke-linejoin="miter"/><path d="M31 29.5l1.5 2.5h-20l1.5-2.5"/><path d="M11 14h23" fill="none" stroke-linejoin="miter"/></g></svg>',
    'B': '<svg viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><g fill="#fff" stroke-linecap="butt"><path d="M9 36c3.39-.97 10.11.43 13.5-2 3.39 2.43 10.11 1.03 13.5 2 0 0 1.65.54 3 2-.68.97-1.65.99-3 .5-3.39-.97-10.11.46-13.5-1-3.39 1.46-10.11.03-13.5 1-1.354.49-2.323.47-3-.5 1.354-1.94 3-2 3-2z"/><path d="M15 32c2.5 2.5 12.5 2.5 15 0 .5-1.5 0-2 0-2 0-2.5-2.5-4-2.5-4 5.5-1.5 6-11.5-5-15.5-11 4-10.5 14-5 15.5 0 0-2.5 1.5-2.5 4 0 0-.5.5 0 2z"/><path d="M25 8a2.5 2.5 0 11-5 0 2.5 2.5 0 115 0z"/></g><path d="M17.5 26h10M15 30h15m-7.5-14.5v5M20 18h5" stroke-linejoin="miter"/></g></svg>',
    'N': '<svg viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10c10.5 1 16.5 8 16 29H15c0-9 10-6.5 8-21" fill="#fff"/><path d="M24 18c.38 2.91-5.55 7.37-8 9-3 2-2.82 4.34-5 4-1.042-.94 1.41-3.04 0-3-1 0 .19 1.23-1 2-1 0-4.003 1-4-4 0-2 6-12 6-12s1.89-1.9 2-3.5c-.73-.994-.5-2-.5-3 1-1 3 2.5 3 2.5h2s.78-1.992 2.5-3c1 0 1 3 1 3" fill="#fff"/><path d="M9.5 25.5a.5.5 0 11-1 0 .5.5 0 111 0z" fill="#000" stroke="#000"/><path d="M15 15.5a.5 1.5 0 11-1 0 .5 1.5 0 111 0z" fill="#000" stroke="#000" transform="matrix(.866 .5 -.5 .866 9.693 -5.173)"/></g></svg>',
    'P': '<svg viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg"><path d="M22.5 9c-2.21 0-4 1.79-4 4 0 .89.29 1.71.78 2.38C17.33 16.5 16 18.59 16 21c0 2.03.94 3.84 2.41 5.03-3 1.06-7.41 5.55-7.41 13.47h23c0-7.92-4.41-12.41-7.41-13.47 1.47-1.19 2.41-3 2.41-5.03 0-2.41-1.33-4.5-3.28-5.62.49-.67.78-1.49.78-2.38 0-2.21-1.79-4-4-4z" fill="#fff" stroke="#000" stroke-width="1.5" stroke-linecap="round"/></svg>',
    'k': '<svg viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22.5 11.63V6" stroke-linejoin="miter"/><path d="M22.5 25s4.5-7.5 3-10.5c0 0-1-2.5-3-2.5s-3 2.5-3 2.5c-1.5 3 3 10.5 3 10.5" fill="#000" stroke-linecap="butt" stroke-linejoin="miter"/><path d="M11.5 37c5.5 3.5 15.5 3.5 21 0v-7s9-4.5 6-10.5c-4-6.5-13.5-3.5-16 4V27v-3.5c-2.5-7.5-12-10.5-16-4-3 6 6 10.5 6 10.5v7z" fill="#000"/><path d="M20 8h5" stroke="#fff" stroke-linejoin="miter"/><path d="M32 29.5s8.5-4 6.03-9.65C34.15 14 25 18 22.5 24.5l.01 2.1-.01-2.1C20 18 9.906 14 6.997 19.85c-2.497 5.65 4.853 9 4.853 9M11.5 30c5.5-3 15.5-3 21 0m-21 3.5c5.5-3 15.5-3 21 0m-21 3.5c5.5-3 15.5-3 21 0" stroke="#fff"/></g></svg>',
    'q': '<svg viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg"><g fill="#000" stroke="#000" stroke-width="1.5" stroke-linejoin="round"><path d="M9 26c8.5-1.5 21-1.5 27 0l2.5-12.5L31 25l-.3-14.1-5.2 13.6-3-14.5-3 14.5-5.2-13.6L14 25 6.5 13.5 9 26z" stroke-linecap="butt"/><path d="M9 26c0 2 1.5 2 2.5 4 1 1.5 1 1 .5 3.5-1.5 1-1 2.5-1 2.5-1.5 1.5.5 2.5.5 2.5 6.5 1 16.5 1 23 0 0 0 1.5-1-.5-2.5 0 0 .5-1.5-1-2.5-.5-2.5-.5-2 .5-3.5 1-2 2.5-2 2.5-4-8.5-1.5-18.5-1.5-27 0z" stroke-linecap="butt"/><path d="M11 38.5a35 35 1 0 0 23 0" fill="none" stroke-linecap="butt"/><path d="M11 29a35 35 1 0 1 23 0M12.5 31.5h20M11.5 34.5a35 35 1 0 0 22 0M10.5 37.5a35 35 1 0 0 24 0" fill="none" stroke="#fff"/><circle cx="6" cy="12" r="2" stroke-linecap="butt"/><circle cx="14" cy="9" r="2" stroke-linecap="butt"/><circle cx="22.5" cy="8" r="2" stroke-linecap="butt"/><circle cx="31" cy="9" r="2" stroke-linecap="butt"/><circle cx="39" cy="12" r="2" stroke-linecap="butt"/></g></svg>',
    'r': '<svg viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg"><g fill="#000" fill-rule="evenodd" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 39h27v-3H9v3zM12.5 32l1.5-2.5h17l1.5 2.5h-20zM12 36v-4h21v4H12z" stroke-linecap="butt"/><path d="M14 29.5v-13h17v13H14z" stroke-linecap="butt" stroke-linejoin="miter"/><path d="M14 16.5L11 14h23l-3 2.5H14zM11 14V9h4v2h5V9h5v2h5V9h4v5H11z" stroke-linecap="butt"/><path d="M12 35.5h21M13 31.5h19M14 29.5h17M14 16.5h17M11 14h23" fill="none" stroke="#fff" stroke-width="1" stroke-linejoin="miter"/></g></svg>',
    'b': '<svg viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><g fill="#000" stroke-linecap="butt"><path d="M9 36c3.39-.97 10.11.43 13.5-2 3.39 2.43 10.11 1.03 13.5 2 0 0 1.65.54 3 2-.68.97-1.65.99-3 .5-3.39-.97-10.11.46-13.5-1-3.39 1.46-10.11.03-13.5 1-1.354.49-2.323.47-3-.5 1.354-1.94 3-2 3-2z"/><path d="M15 32c2.5 2.5 12.5 2.5 15 0 .5-1.5 0-2 0-2 0-2.5-2.5-4-2.5-4 5.5-1.5 6-11.5-5-15.5-11 4-10.5 14-5 15.5 0 0-2.5 1.5-2.5 4 0 0-.5.5 0 2z"/><path d="M25 8a2.5 2.5 0 11-5 0 2.5 2.5 0 115 0z"/></g><path d="M17.5 26h10M15 30h15m-7.5-14.5v5M20 18h5" stroke="#fff" stroke-linejoin="miter"/></g></svg>',
    'n': '<svg viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg"><g fill="none" fill-rule="evenodd" stroke="#000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10c10.5 1 16.5 8 16 29H15c0-9 10-6.5 8-21" fill="#000"/><path d="M24 18c.38 2.91-5.55 7.37-8 9-3 2-2.82 4.34-5 4-1.042-.94 1.41-3.04 0-3-1 0 .19 1.23-1 2-1 0-4.003 1-4-4 0-2 6-12 6-12s1.89-1.9 2-3.5c-.73-.994-.5-2-.5-3 1-1 3 2.5 3 2.5h2s.78-1.992 2.5-3c1 0 1 3 1 3" fill="#000"/><path d="M9.5 25.5a.5.5 0 11-1 0 .5.5 0 111 0z" fill="#fff" stroke="#fff"/><path d="M15 15.5a.5 1.5 0 11-1 0 .5 1.5 0 111 0z" fill="#fff" stroke="#fff" transform="matrix(.866 .5 -.5 .866 9.693 -5.173)"/><path d="M24.55 10.4l-.45 1.45.5.15c3.15 1 5.65 2.49 7.9 6.75S35.75 29.06 35.25 39l-.05.5h2.25l.05-.5c.5-10.06-.88-16.85-3.25-21.34-2.37-4.49-5.79-6.64-9.19-7.16l-.51-.1z" fill="#fff" stroke="none"/></g></svg>',
    'p': '<svg viewBox="0 0 45 45" xmlns="http://www.w3.org/2000/svg"><path d="M22.5 9c-2.21 0-4 1.79-4 4 0 .89.29 1.71.78 2.38C17.33 16.5 16 18.59 16 21c0 2.03.94 3.84 2.41 5.03-3 1.06-7.41 5.55-7.41 13.47h23c0-7.92-4.41-12.41-7.41-13.47 1.47-1.19 2.41-3 2.41-5.03 0-2.41-1.33-4.5-3.28-5.62.49-.67.78-1.49.78-2.38 0-2.21-1.79-4-4-4z" fill="#000" stroke="#000" stroke-width="1.5" stroke-linecap="round"/></svg>'
  };

  // Convert a from→to move into Standard Algebraic Notation given the pre-move state.
  // Handles castling, pawn captures (incl. en passant), promotion, piece disambiguation.
  function moveToSan(preState, fromSq, toSq, postFen) {
    const [fr, ff] = sqToIdx(fromSq);
    const [tr, tf] = sqToIdx(toSq);
    const piece = getPiece(preState.board, fr, ff);
    if (!piece) return fromSq + toSq;
    const isWhite = piece === piece.toUpperCase();
    const type = piece.toLowerCase();
    const target = getPiece(preState.board, tr, tf);

    // Castling — king moves 2 files
    if (type === 'k' && Math.abs(tf - ff) === 2) {
      return tf > ff ? 'O-O' : 'O-O-O';
    }

    if (type === 'p') {
      let san = '';
      const isCapture = !!target || (preState.ep !== '-' && toSq === preState.ep);
      san = isCapture ? FILES[ff] + 'x' + toSq : toSq;
      const promRank = isWhite ? 7 : 0;
      if (tr === promRank && postFen) {
        try {
          const post = parseFen(postFen);
          const newPc = getPiece(post.board, tr, tf);
          if (newPc && newPc.toLowerCase() !== 'p') san += '=' + newPc.toUpperCase();
        } catch (e) {}
      }
      return san;
    }

    const pieceLetter = piece.toUpperCase();
    // Disambiguate against other same-type pieces that could legally reach toSq.
    const candidates = [];
    for (let r = 0; r < 8; r++) {
      for (let f = 0; f < 8; f++) {
        if (r === fr && f === ff) continue;
        if (getPiece(preState.board, r, f) !== piece) continue;
        const moves = pieceMoves(preState, r, f);
        if (moves.some(m => m[0] === tr && m[1] === tf)) candidates.push([r, f]);
      }
    }
    let disamb = '';
    if (candidates.length > 0) {
      const sameFile = candidates.some(c => c[1] === ff);
      const sameRank = candidates.some(c => c[0] === fr);
      if (!sameFile) disamb = FILES[ff];
      else if (!sameRank) disamb = String(fr + 1);
      else disamb = fromSq;
    }
    return pieceLetter + disamb + (target ? 'x' : '') + toSq;
  }

  class Board {
    constructor(elId, opts) {
      this.el = (typeof elId === 'string') ? document.getElementById(elId) : elId;
      this.opts = opts || {};
      this.flipped = !!this.opts.flipped;
      this.fen = this.opts.position || 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
      this.selected = null;
      this.legalTargets = [];
      this.size = this.opts.size || 480;
      this.coords = this.opts.coords !== false;
      this.arrows = this.opts.arrows || [];
      this.highlights = this.opts.highlights || [];
      this.drawColor = this.opts.drawColor || 'green';
      // drawing state for arrow / highlight authoring
      this.drawingFrom = null;
      this.render();
    }

    setPosition(fen) {
      this.fen = fen || 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
      this.selected = null;
      this.legalTargets = [];
      this.render();
    }

    setOverlay(arrows, highlights) {
      this.arrows = arrows || [];
      this.highlights = highlights || [];
      this.render();
    }

    clearOverlay() { this.arrows = []; this.highlights = []; this.render(); }

    flip() { this.flipped = !this.flipped; this.render(); }

    setDrawColor(color) { this.drawColor = color || 'green'; }

    render() {
      const state = parseFen(this.fen);
      const sqSize = this.size / 8;
      this.el.className = 'lc-board';
      this.el.style.width = this.size + 'px';
      this.el.style.height = this.size + 'px';
      this.el.innerHTML = '';
      // build a map of highlights by sq for quick lookup
      const hlMap = {};
      (this.highlights || []).forEach(h => { hlMap[h.sq] = (h.color || 'green'); });

      const rRange = this.flipped ? [0,1,2,3,4,5,6,7] : [7,6,5,4,3,2,1,0];
      const fRange = this.flipped ? [7,6,5,4,3,2,1,0] : [0,1,2,3,4,5,6,7];

      for (const r of rRange) {
        for (const f of fRange) {
          const sq = idxToSq(r, f);
          const pc = getPiece(state.board, r, f);
          const cell = document.createElement('div');
          const isLight = (f + r) % 2 === 1;
          cell.className = 'lc-sq ' + (isLight ? 'light' : 'dark');
          cell.dataset.sq = sq;

          if (this.selected === sq) cell.classList.add('sel');
          if (this.legalTargets.indexOf(sq) !== -1) {
            cell.classList.add(pc ? 'cap' : 'dot');
          }
          if (hlMap[sq]) cell.classList.add('hl-' + hlMap[sq]);

          if (pc) {
            const span = document.createElement('span');
            span.className = 'lc-piece';
            span.innerHTML = PIECES[pc] || '';
            cell.appendChild(span);
          }

          // Coordinates on outer edge
          if (this.coords) {
            const fileChar = FILES[f];
            const isLastRank = this.flipped ? (r === 7) : (r === 0);
            const isFirstFile = this.flipped ? (f === 7) : (f === 0);
            if (isLastRank) {
              const c = document.createElement('span');
              c.className = 'lc-coord lc-coord-file ' + (isLight ? 'on-light' : 'on-dark');
              c.textContent = fileChar;
              cell.appendChild(c);
            }
            if (isFirstFile) {
              const c = document.createElement('span');
              c.className = 'lc-coord lc-coord-rank ' + (isLight ? 'on-light' : 'on-dark');
              c.textContent = (r + 1);
              cell.appendChild(c);
            }
          }

          if (this.opts.draggable) {
            cell.addEventListener('click', () => this.handleClick(sq));
            cell.style.cursor = 'pointer';
          }

          // Right-click drawing — for the coach when this.opts.draw is true
          if (this.opts.draw) {
            cell.addEventListener('contextmenu', e => e.preventDefault());
            cell.addEventListener('mousedown', e => {
              if (e.button !== 2) return;
              e.preventDefault();
              this.drawingFrom = sq;
            });
            cell.addEventListener('mouseup', e => {
              if (e.button !== 2 || !this.drawingFrom) return;
              e.preventDefault();
              const from = this.drawingFrom; const to = sq;
              this.drawingFrom = null;
              if (from === to) {
                const color = this.drawColor || 'green';
                const idx = (this.highlights || []).findIndex(h => h.sq === sq);
                if (idx !== -1 && this.highlights[idx].color === color) this.highlights.splice(idx, 1);
                else if (idx !== -1) this.highlights[idx] = { sq, color };
                else this.highlights.push({ sq, color });
              } else {
                const color = this.drawColor || 'green';
                const idx = (this.arrows || []).findIndex(a => a.from === from && a.to === to);
                if (idx !== -1 && this.arrows[idx].color === color) this.arrows.splice(idx, 1);
                else if (idx !== -1) this.arrows[idx] = { from, to, color };
                else this.arrows.push({ from, to, color });
              }
              if (this.opts.onDraw) this.opts.onDraw({ arrows: this.arrows, highlights: this.highlights });
              this.render();
            });
          }

          this.el.appendChild(cell);
        }
      }

      // Arrow overlay (rendered above cells)
      if ((this.arrows || []).length > 0) {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'lc-overlay');
        svg.setAttribute('viewBox', '0 0 800 800');
        svg.setAttribute('preserveAspectRatio', 'none');
        const sqViewSize = 100;
        const orientFile = (f) => this.flipped ? (7 - f) : f;
        const orientRank = (r) => this.flipped ? r : (7 - r);
        const COLORS = { green: '#15803d', red: '#dc2626', blue: '#2563eb', yellow: '#ca8a04' };
        // Define arrowhead marker
        const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        Object.entries(COLORS).forEach(([name, col]) => {
          defs.innerHTML += `<marker id="arr-${name}" viewBox="0 0 10 10" refX="6" refY="5" markerWidth="4" markerHeight="4" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="${col}"/></marker>`;
        });
        svg.appendChild(defs);
        for (const arr of this.arrows) {
          const [fr, ff] = sqToIdx(arr.from);
          const [tr, tf] = sqToIdx(arr.to);
          const fx = orientFile(ff) * sqViewSize + sqViewSize/2;
          const fy = orientRank(fr) * sqViewSize + sqViewSize/2;
          const tx = orientFile(tf) * sqViewSize + sqViewSize/2;
          const ty = orientRank(tr) * sqViewSize + sqViewSize/2;
          // Trim the arrow short of the target square so the arrowhead sits inside
          const dx = tx - fx, dy = ty - fy;
          const len = Math.sqrt(dx*dx + dy*dy);
          const trim = sqViewSize * 0.25;
          const ex = tx - (dx/len) * trim;
          const ey = ty - (dy/len) * trim;
          const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
          line.setAttribute('x1', fx); line.setAttribute('y1', fy);
          line.setAttribute('x2', ex); line.setAttribute('y2', ey);
          line.setAttribute('stroke', COLORS[arr.color || 'green']);
          line.setAttribute('stroke-width', '12');
          line.setAttribute('stroke-linecap', 'round');
          line.setAttribute('opacity', '0.85');
          line.setAttribute('marker-end', `url(#arr-${arr.color || 'green'})`);
          svg.appendChild(line);
        }
        this.el.appendChild(svg);
      }
    }

    handleClick(sq) {
      const state = parseFen(this.fen);
      const [r, f] = sqToIdx(sq);
      const pc = getPiece(state.board, r, f);
      const legalMovesForSquare = (rank, file) => {
        if (typeof this.opts.getLegalMoves === 'function') {
          return this.opts.getLegalMoves(state, rank, file) || [];
        }
        return pieceMoves(state, rank, file);
      };

      if (this.selected) {
        const wasSelected = this.selected;
        if (this.legalTargets.indexOf(sq) !== -1) {
          // Make the move locally (so the renderer flips), then call onMove
          const newState = parseFen(this.fen);
          const [sr, sf] = sqToIdx(wasSelected);
          const moving = getPiece(newState.board, sr, sf);
          // Promotion handling: if pawn reaches last rank, promote to queen
          let promotion = null;
          if (moving && moving.toLowerCase() === 'p' && (r === 0 || r === 7)) promotion = 'q';
          // En passant
          if (moving && moving.toLowerCase() === 'p' && newState.ep !== '-' && sq === newState.ep) {
            const epDir = (moving === 'P') ? -1 : 1;
            setPiece(newState.board, r + epDir, f, null);
          }
          setPiece(newState.board, sr, sf, null);
          const finalPiece = promotion ? ((moving === 'P') ? promotion.toUpperCase() : promotion) : moving;
          setPiece(newState.board, r, f, finalPiece);
          if (moving && moving.toLowerCase() === 'p' && Math.abs(r - sr) === 2) {
            newState.ep = idxToSq((sr + r) / 2, sf);
          } else {
            newState.ep = '-';
          }
          newState.turn = (newState.turn === 'w') ? 'b' : 'w';
          if (newState.turn === 'w') newState.fullMove++;
          this.fen = toFen(newState);
          this.selected = null;
          this.legalTargets = [];
          this.render();
          if (this.opts.onMove) this.opts.onMove(wasSelected, sq, this.fen);
          return;
        }
        // Switch to a different piece
        this.selected = null;
        this.legalTargets = [];
        if (pc) {
          const isWhite = pc === pc.toUpperCase();
          if ((state.turn === 'w' && isWhite) || (state.turn === 'b' && !isWhite)) {
            this.selected = sq;
            this.legalTargets = legalMovesForSquare(r, f).map(([tr, tf]) => idxToSq(tr, tf));
          }
        }
        this.render();
      } else if (pc) {
        const isWhite = pc === pc.toUpperCase();
        if ((state.turn === 'w' && isWhite) || (state.turn === 'b' && !isWhite)) {
          this.selected = sq;
          this.legalTargets = legalMovesForSquare(r, f).map(([tr, tf]) => idxToSq(tr, tf));
          this.render();
        }
      }
    }
  }

  // ── Position editor ───────────────────────────────────────────────────
  // A piece-palette + click-to-place editor that produces a FEN.
  // Usage:
  //   const ed = new PositionEditor('host-el', { startFen: '...', onSave: (fen) => { ... }, onCancel: () => {} });
  // The editor builds its own UI inside the host element.
  class PositionEditor {
    constructor(host, opts) {
      this.host = (typeof host === 'string') ? document.getElementById(host) : host;
      this.opts = opts || {};
      this.fen = this.opts.startFen || '8/8/8/8/8/8/8/8 w - - 0 1';
      this.state = parseFen(this.fen);
      this.selectedPiece = 'P';   // currently-active palette piece
      this.eraser = false;
      this.boardId = 'pe-board-' + Math.random().toString(36).slice(2, 8);
      this.size = this.opts.size || 360;
      this.build();
    }

    build() {
      this.host.innerHTML = `
        <div class="pe-wrap">
          <div id="${this.boardId}"></div>
          <div class="pe-side">
            <div class="pe-palette" id="${this.boardId}-pal"></div>
            <div class="pe-opts">
              <label><input type="radio" name="${this.boardId}-turn" value="w"> White to move</label>
              <label><input type="radio" name="${this.boardId}-turn" value="b"> Black to move</label>
              <hr style="border:none;border-top:1px solid #e2e8f0;margin:4px 0">
              <div style="font-weight:600;color:#334155;font-size:11px">Castling rights</div>
              <label><input type="checkbox" data-cast="K"> White O-O (K)</label>
              <label><input type="checkbox" data-cast="Q"> White O-O-O (Q)</label>
              <label><input type="checkbox" data-cast="k"> Black O-O (k)</label>
              <label><input type="checkbox" data-cast="q"> Black O-O-O (q)</label>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <button type="button" class="btn btn-xs btn-ghost" data-act="clear">Clear board</button>
              <button type="button" class="btn btn-xs btn-ghost" data-act="reset">Starting</button>
              <button type="button" class="btn btn-xs btn-ghost" data-act="flip">Flip</button>
            </div>
            <div class="pe-fen-box" id="${this.boardId}-fen"></div>
            <div style="display:flex;gap:6px;justify-content:flex-end">
              <button type="button" class="btn btn-ghost btn-sm" data-act="cancel">Cancel</button>
              <button type="button" class="btn btn-primary btn-sm" data-act="save">Save position</button>
            </div>
          </div>
        </div>
      `;
      this.buildPalette();
      this.boardEl = this.host.querySelector('#' + this.boardId);
      // Plain non-draggable board with click-to-place handlers (we add our own listeners)
      this.board = new Board(this.boardEl, { size: this.size, position: this.fen, draggable: false, coords: true });
      this.attachClickHandlers();
      // Wire side panel
      const turnRadios = this.host.querySelectorAll('input[name="' + this.boardId + '-turn"]');
      turnRadios.forEach(r => {
        r.checked = (r.value === this.state.turn);
        r.addEventListener('change', () => { this.state.turn = r.value; this.refreshFen(); });
      });
      this.host.querySelectorAll('input[data-cast]').forEach(c => {
        c.checked = this.state.castling.indexOf(c.dataset.cast) !== -1;
        c.addEventListener('change', () => this.recomputeCastling());
      });
      this.host.querySelectorAll('button[data-act]').forEach(btn => {
        btn.addEventListener('click', e => {
          const a = btn.dataset.act;
          if (a === 'clear')  this.clearBoard();
          if (a === 'reset')  this.resetStarting();
          if (a === 'flip')   { this.board.flip(); this.attachClickHandlers(); }
          if (a === 'save')   this.opts.onSave && this.opts.onSave(this.currentFen());
          if (a === 'cancel') this.opts.onCancel && this.opts.onCancel();
        });
      });
      this.refreshFen();
    }

    buildPalette() {
      const pal = this.host.querySelector('#' + this.boardId + '-pal');
      const order = [
        ['K','k'], ['Q','q'], ['R','r'], ['B','b'], ['N','n'], ['P','p']
      ];
      pal.innerHTML = '';
      order.forEach(pair => {
        pair.forEach(pc => {
          const cell = document.createElement('div');
          cell.className = 'pe-pc';
          cell.dataset.piece = pc;
          cell.innerHTML = PIECES[pc] || '';
          if (pc === this.selectedPiece && !this.eraser) cell.classList.add('sel');
          cell.addEventListener('click', () => this.selectPiece(pc));
          pal.appendChild(cell);
        });
      });
      // Eraser
      const er = document.createElement('div');
      er.className = 'pe-pc eraser';
      er.textContent = 'Erase';
      er.style.gridColumn = '1 / -1';
      er.style.width = 'auto';
      er.dataset.piece = '';
      if (this.eraser) er.classList.add('sel');
      er.addEventListener('click', () => this.selectEraser());
      pal.appendChild(er);
    }

    selectPiece(pc) {
      this.selectedPiece = pc; this.eraser = false;
      this.host.querySelectorAll('.pe-pc').forEach(el => el.classList.toggle('sel', el.dataset.piece === pc && !el.classList.contains('eraser')));
    }

    selectEraser() {
      this.eraser = true;
      this.host.querySelectorAll('.pe-pc').forEach(el => el.classList.remove('sel'));
      this.host.querySelector('.pe-pc.eraser').classList.add('sel');
    }

    attachClickHandlers() {
      // Re-bind because Board.render() may have rebuilt cells.
      // Use event delegation on the board element instead — set up once.
      if (this._delegated) return;
      this._delegated = true;
      this.boardEl.addEventListener('click', e => {
        const sq = e.target.closest('.lc-sq');
        if (!sq) return;
        this.placeOnSquare(sq.dataset.sq);
      });
      this.boardEl.addEventListener('contextmenu', e => {
        const sq = e.target.closest('.lc-sq');
        if (!sq) return;
        e.preventDefault();
        // Right-click always erases
        this.placeOnSquare(sq.dataset.sq, true);
      });
    }

    placeOnSquare(sq, forceErase) {
      const [r, f] = sqToIdx(sq);
      if (forceErase || this.eraser) {
        setPiece(this.state.board, r, f, null);
      } else {
        setPiece(this.state.board, r, f, this.selectedPiece);
      }
      this.recomputeCastling(); // king/rook moves may invalidate castling
      this.applyState();
    }

    clearBoard() {
      this.state = parseFen('8/8/8/8/8/8/8/8 w - - 0 1');
      this.applyState();
    }

    resetStarting() {
      this.state = parseFen('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');
      this.applyState();
    }

    recomputeCastling() {
      let cast = '';
      this.host.querySelectorAll('input[data-cast]').forEach(c => {
        if (c.checked) cast += c.dataset.cast;
      });
      // Only allow rights for which the king/rook is actually in place
      const b = this.state.board;
      const wKingHome = getPiece(b, 0, 4) === 'K';
      const bKingHome = getPiece(b, 7, 4) === 'k';
      let valid = '';
      if (cast.indexOf('K') !== -1 && wKingHome && getPiece(b, 0, 7) === 'R') valid += 'K';
      if (cast.indexOf('Q') !== -1 && wKingHome && getPiece(b, 0, 0) === 'R') valid += 'Q';
      if (cast.indexOf('k') !== -1 && bKingHome && getPiece(b, 7, 7) === 'r') valid += 'k';
      if (cast.indexOf('q') !== -1 && bKingHome && getPiece(b, 7, 0) === 'r') valid += 'q';
      this.state.castling = valid || '-';
      // Reflect any auto-stripped rights back into checkboxes
      this.host.querySelectorAll('input[data-cast]').forEach(c => {
        c.checked = this.state.castling.indexOf(c.dataset.cast) !== -1;
      });
      this.refreshFen();
    }

    applyState() {
      const fen = toFen(this.state);
      this.board.setPosition(fen);
      this.refreshFen();
    }

    refreshFen() {
      const fen = toFen(this.state);
      const box = this.host.querySelector('#' + this.boardId + '-fen');
      if (box) box.textContent = fen;
    }

    currentFen() { return toFen(this.state); }
  }

  window.MiniChess = {
    Board: Board,
    PositionEditor: PositionEditor,
    parsePGN: parsePGN,
    parsePGNFile: parsePGNFile,
    parsePGNGame: parsePGNGame,
    parseFen: parseFen,
    toFen: toFen,
    applySAN: applySAN,
    moveToSan: moveToSan,
    sqToIdx: sqToIdx,
    idxToSq: idxToSq,
    getPiece: getPiece,
    setPiece: setPiece,
    pieceMoves: pieceMoves
  };
})();
</script>
