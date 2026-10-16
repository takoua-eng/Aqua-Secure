{{-- Styles communs aux PDF (dompdf : CSS simple, police DejaVu Sans pour les accents) --}}
<style>
    @page { margin: 28px 32px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1d273b; }
    h1 { font-size: 17px; margin: 0 0 2px; color: #0b3d91; }
    h2 { font-size: 12.5px; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #dce1e7; }
    .sous-titre { color: #667382; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; background: #f1f5f9; color: #475569; font-weight: bold; padding: 5px 6px; font-size: 9px; }
    td { padding: 4px 6px; border-bottom: 1px solid #e9ecef; vertical-align: top; }
    .droite { text-align: right; }
    .mono { font-family: "DejaVu Sans Mono", monospace; }
    .muet { color: #667382; }
    .badge { padding: 1px 5px; border-radius: 3px; font-size: 8.5px; font-weight: bold; }
    .green  { background: #d7f2db; color: #1c6b2a; }
    .yellow { background: #fdf0c8; color: #8a5b00; }
    .orange { background: #fde2cf; color: #9a3d0b; }
    .red    { background: #f8d7d7; color: #9b1c1c; }
    .azure, .secondary { background: #e0ecfb; color: #1c4e8c; }
    .tuiles td { border: 1px solid #dce1e7; text-align: center; padding: 8px; }
    .tuiles .valeur { font-size: 15px; font-weight: bold; }
    .pied { position: fixed; bottom: -14px; left: 0; right: 0; font-size: 8px; color: #94a3b8; text-align: center; }
</style>
