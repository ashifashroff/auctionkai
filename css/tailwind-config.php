<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        ak: {
          bg: '#0A1420',
          bg2: '#07101A',
          card: '#111E2D',
          border: '#1E3A5F',
          gold: '#D4A84B',
          text: '#E8DCC8',
          text2: '#A8C4D8',
          muted: '#6A88A0',
          muted2: '#3A5570',
          green: '#4CAF82',
          red: '#CC7777',
          infield: '#0A1724',
        }
      },
      fontFamily: {
        sans: ['Noto Sans JP', 'sans-serif'],
        mono: ['Space Mono', 'monospace'],
      },
      animation: {
        'fade-in': 'fadeIn .3s ease-out',
        'fade-in-up': 'fadeInUp .4s ease-out',
        'slide-down': 'slideDown .25s ease-out',
        'pulse-gold': 'pulse-gold 2s infinite',
        'spin': 'spin .6s linear infinite',
      }
    }
  }
}
</script>
