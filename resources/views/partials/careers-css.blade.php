<style>
        /* A careers site is a shop window, not a back office: it goes light,
           with the navy and red kept for type and accents. The rest of the
           HRIS stays dark - this page is the one outsiders see. */
        .careers {
            --ink:    #0C1626;
            --muted:  #5A6A80;
            --line:   #E3E8EF;
            --brand:  #E31B23;
            --wash:   #F6F8FB;

            background: #fff;
            color: var(--ink);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .careers a { text-decoration: none; }

        .careers__wrap {
            width: 100%;
            max-width: 76rem;
            margin: 0 auto;
            padding: 0 32px;
        }

        /* Uniqlo's device: an enormous headline that breaks across two lines,
           with everything else deliberately quiet around it. */
        .careers__display {
            font-family: var(--font-head, 'Space Grotesk', system-ui, sans-serif);
            font-weight: 700;
            line-height: 1.02;
            letter-spacing: -0.035em;
            margin: 0;
        }

        .careers__eyebrow {
            display: block;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
            color: var(--brand);
            margin-bottom: 18px;
        }

        /* ----------------------------------------------------- careers nav */
        /* One bar, the way Uniqlo does it: fixed, transparent while it sits on
           the hero photograph, turning solid white the moment you scroll past.
           The page used to carry the dark site header as well - two bars and
           118px of chrome before any content. */
        /* Solid is the DEFAULT, and transparent is the exception the home page
           opts into. It was the other way round, which meant any failure -
           a cached page, a class that did not apply - left white type on a
           white ground and a bar you could not read. This way the worst case
           is a solid bar where a transparent one was wanted. */
        .cnav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, .96);
            -webkit-backdrop-filter: saturate(180%) blur(12px);
            backdrop-filter: saturate(180%) blur(12px);
            box-shadow: 0 1px 0 var(--line), 0 6px 22px rgba(12, 22, 38, .07);
            transition: background-color .3s ease, box-shadow .3s ease;
        }

        .cnav__inner {
            display: flex;
            align-items: center;
            gap: 32px;
            height: 82px;
        }

        .cnav__brand {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--ink);
            font-size: .9375rem;
            letter-spacing: -0.01em;
            transition: color .3s ease;
        }

        .cnav__brand img {
            width: 38px;
            height: 38px;
            object-fit: contain;
            border-radius: 50%;
            background: #fff;
            padding: 2px;
        }

        .cnav__brand b { font-weight: 700; }

        .cnav__links {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0 auto;
            min-width: 0;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .cnav__links::-webkit-scrollbar { display: none; }

        .cnav__link {
            flex-shrink: 0;
            padding: 8px 12px;
            border-radius: 6px;
            color: var(--ink);
            font-size: 1rem;
            font-weight: 500;
            white-space: nowrap;
            transition: color .2s ease, opacity .2s ease;
        }

        .cnav__link:hover { opacity: .7; }

        /* Colour alone marks the section, as on Uniqlo. Their bar is light, so
           brand red reads on it; ours starts transparent over a dark
           photograph, where #E31B23 goes muddy and the active link all but
           disappears. Over the photo it takes the lighter tint, and the full
           brand red only once the bar turns white. */
        .cnav__link.is-active { color: var(--brand); }

        /* On the photograph the full brand red goes muddy, so the active link
           takes a lighter tint there only. */
        .cnav.is-over-hero .cnav__link.is-active { color: #FF7A80; }

        .cnav__right { flex-shrink: 0; display: flex; align-items: center; gap: 18px; }

        .cnav__staff {
            color: var(--muted);
            font-size: .875rem;
            font-weight: 500;
            white-space: nowrap;
            transition: color .3s ease;
        }

        .cnav__staff:hover { color: #fff; }

        .cnav__apply {
            display: inline-flex;
            align-items: center;
            height: 44px;
            padding: 0 28px;
            border-radius: 999px;
            background: var(--brand);
            color: #fff;
            font-size: .9375rem;
            font-weight: 700;
            white-space: nowrap;
            transition: background-color .15s ease;
        }

        .cnav__apply:hover { background: #B5141A; }

        /* Only while it sits on the home page's photograph. */
        .cnav.is-over-hero {
            background: transparent;
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
            box-shadow: none;
        }

        .cnav.is-over-hero .cnav__brand,
        .cnav.is-over-hero .cnav__link { color: #fff; }
        .cnav.is-over-hero .cnav__link { color: rgba(255, 255, 255, .92); }
        .cnav.is-over-hero .cnav__staff { color: rgba(255, 255, 255, .8); }

        /* One bar now, so anchors need to clear 82px rather than 124. */
        .careers [id] { scroll-margin-top: 96px; }

        /* On a wide screen the menu wrapper is not a box at all - the links and
           the buttons are laid out by .cnav__inner exactly as before. */
        .cnav__menu { display: contents; }

        .cnav__burger { display: none; }

        /* The staff link is what a visitor needs least, so it is what goes when
           the room runs out - the section names never truncate. The wordmark
           used to go next; at "IC Careers" it is small enough to keep, and the
           phone menu takes over before the bar is tight anyway. */
        @media (max-width: 1180px) { .cnav__staff { display: none; } .cnav__inner { gap: 20px; } }
        @media (max-width: 1040px) { .cnav__inner { gap: 16px; } }

        /* ------------------------------------------------- the phone menu */
        /* A drawer off the right edge, the way Uniqlo's phone menu opens: the
           page stays visible down the left, dimmed, and the menu carries its
           own head with a close button rather than relying on the burger
           turning into an X somewhere behind it. */
        .cnav__menu-head,
        .cnav__scrim { display: none; }

        @media (max-width: 860px) {
            .cnav__inner { height: 68px; gap: 12px; justify-content: space-between; }

            .cnav__burger {
                display: inline-flex;
                flex-direction: column;
                justify-content: center;
                gap: 5px;
                width: 44px;
                height: 44px;
                padding: 0 10px;
                border: 0;
                background: none;
                cursor: pointer;
            }

            .cnav__burger span {
                display: block;
                height: 2px;
                border-radius: 2px;
                background: var(--ink);
                transition: background-color .3s ease;
            }

            .cnav.is-over-hero .cnav__burger span { background: #fff; }

            .cnav__scrim {
                display: block;
                position: fixed;
                inset: 0;
                /* Under the bar, not over it. .cnav is fixed with a z-index of
                   its own, which makes it a stacking context: the drawer lives
                   inside that context, so its z-index cannot lift it above a
                   scrim that outranks .cnav itself. At 1100 the scrim was
                   painting over the drawer and greying the menu out along with
                   the page. Anything below 1000 sits behind the whole bar. */
                z-index: 990;
                background: rgba(12, 22, 38, .45);
            }

            .cnav__scrim[hidden] { display: none; }

            .cnav__menu {
                display: flex;
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                z-index: 1;
                width: 88%;
                max-width: 24rem;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                padding: 22px 22px 28px;
                overflow-y: auto;
                /* Solid white whatever the bar is doing. Over a photograph the
                   drawer would otherwise inherit the transparent bar and put
                   navy type on a shop floor. */
                background: #fff;
                box-shadow: -18px 0 40px rgba(12, 22, 38, .18);
                /* Off-screen rather than display:none, so it slides. */
                transform: translateX(100%);
                transition: transform .28s ease;
                visibility: hidden;
            }

            .cnav.is-open .cnav__menu { transform: translateX(0); visibility: visible; }

            .cnav__menu-head {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                padding-bottom: 20px;
                margin-bottom: 4px;
                border-bottom: 1px solid var(--line);
            }

            .cnav__menu-eyebrow {
                display: block;
                color: var(--brand);
                font-size: .6875rem;
                font-weight: 700;
                letter-spacing: .16em;
                text-transform: uppercase;
            }

            .cnav__menu-title {
                display: block;
                margin-top: 10px;
                font-family: var(--font-head, 'Space Grotesk', system-ui, sans-serif);
                font-size: 1.25rem;
                font-weight: 700;
                letter-spacing: -0.02em;
                color: var(--ink);
            }

            .cnav__close {
                flex-shrink: 0;
                width: 38px;
                height: 38px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid var(--line);
                border-radius: 50%;
                background: #fff;
                color: var(--ink);
                font-size: 1rem;
                cursor: pointer;
            }

            .cnav__links {
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                margin: 0;
                overflow: visible;
            }

            /* The red rule down the left of the current page, as on theirs.
               It is transparent on the rest so nothing shifts sideways when
               the active one takes it. */
            .cnav__link,
            .cnav.is-over-hero .cnav__link {
                padding: 16px 4px 16px 14px;
                border-bottom: 1px solid var(--line);
                border-left: 3px solid transparent;
                border-radius: 0;
                color: var(--ink);
                font-size: 1.0625rem;
                font-weight: 600;
            }

            .cnav__link.is-active,
            .cnav.is-over-hero .cnav__link.is-active {
                border-left-color: var(--brand);
                color: var(--brand);
            }

            .cnav__right {
                flex-direction: column-reverse;
                align-items: stretch;
                gap: 16px;
                margin-top: 26px;
            }

            .cnav__staff,
            .cnav.is-over-hero .cnav__staff {
                display: block;
                color: var(--muted);
                font-size: .9375rem;
                text-align: center;
            }

            .cnav__apply {
                height: 54px;
                justify-content: center;
                border-radius: 6px;
                font-size: 1rem;
            }
        }

        /* ---------------------------------------------------------- hero */
        .hero {
            position: relative;
            isolation: isolate;
            min-height: 78vh;
            display: flex;
            /* Centred in the space under the bar, not pinned to the floor of
               it. Sitting at flex-end left a third of the photograph empty
               above the headline on any tall screen, and the taller the screen
               the worse it got. The extra top padding is the fixed bar's own
               height, so the type is centred in what is actually visible. */
            align-items: center;
            padding: 122px 0 58px;
            background: var(--ink);
            overflow: hidden;
        }

        .hero__img {
            position: absolute;
            inset: 0;
            z-index: -2;
            background-size: cover;
            /* 40% down suits a wide shot of a room, where the interest is
               above the middle. */
            background-position: center 40%;
        }

        /* Without a photograph this is not an empty box - it is a deep navy
           field with a red wash, which is a deliberate-looking hero on its own. */
        .hero__img--empty {
            background:
                radial-gradient(120% 90% at 78% 15%, rgba(227, 27, 35, .42) 0%, transparent 62%),
                linear-gradient(145deg, #0C1626 0%, #17233A 55%, #0C1626 100%);
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            /* Two washes: one lifting off the bottom for the headline and the
               lede, and a flat one over the whole frame. Without the flat one
               the type lands on whatever the photograph happens to be doing
               there - which on a busy shop floor is not readable. Heavier now
               that the bar above it is transparent and carries white type. */
            background:
                linear-gradient(to top,
                    rgba(12, 22, 38, .96) 0%,
                    rgba(12, 22, 38, .82) 34%,
                    rgba(12, 22, 38, .46) 68%,
                    rgba(12, 22, 38, .58) 100%),
                rgba(12, 22, 38, .18);
        }

        .hero__eyebrow { color: #FF6B72; }

        .hero h1 {
            font-size: clamp(2.6rem, 7vw, 5.25rem);
            color: #fff;
            max-width: 15ch;
        }

        .hero__lede {
            margin: 22px 0 0;
            max-width: 40ch;
            color: #C3CDDC;
            font-size: 1.125rem;
            line-height: 1.6;
        }

        /* --------------------------------------------------------- search */
        .search {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 36px;
            max-width: 46rem;
        }

        .search__field {
            position: relative;
            flex: 1 1 22rem;
            min-width: 0;
        }

        .search__field i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #8795A8;
            pointer-events: none;
        }

        .search input {
            width: 100%;
            height: 60px;
            padding: 0 20px 0 50px;
            border: 0;
            border-radius: 6px;
            background: #fff;
            color: var(--ink);
            font-size: 1rem;
            font-family: inherit;
        }

        .search input::placeholder { color: #8795A8; }
        .search input:focus { outline: 3px solid rgba(227, 27, 35, .55); outline-offset: 2px; }

        .search__go {
            height: 60px;
            padding: 0 38px;
            border: 0;
            border-radius: 6px;
            background: var(--brand);
            color: #fff;
            font: inherit;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color .15s ease;
        }

        .search__go:hover { background: #B5141A; }

        /* ------------------------------------------------------- sections */
        .section { padding: 104px 0; }
        .section--wash { background: var(--wash); }

        .section__head { max-width: 46rem; margin-bottom: 56px; }

        .section__head h2 {
            font-size: clamp(2rem, 4.2vw, 3.25rem);
            font-family: var(--font-head, 'Space Grotesk', system-ui, sans-serif);
            font-weight: 700;
            line-height: 1.05;
            letter-spacing: -0.03em;
            margin: 0;
        }

        .section__head p {
            margin: 18px 0 0;
            color: var(--muted);
            font-size: 1.0625rem;
            line-height: 1.65;
        }

        /* ------------------------------------------------------- welcome */
        .welcome { padding-bottom: 0; }

        .welcome__list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            /* Two across, like the doors on the home page. Four made each card
               327px wide and the photograph in it a thumbnail. */
            grid-template-columns: repeat(2, 1fr);
            gap: 22px;
        }


        .welcome__item {
            display: flex;
            flex-direction: column;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
        }

        .welcome__pic {
            aspect-ratio: 4 / 3;
            background-size: cover;
            background-position: center;
            background-color: var(--ink);
        }

        /* No photograph yet: a navy panel with the icon, deliberate-looking
           rather than a hole where a picture should be. */
        .welcome__pic--icon {
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, .34);
            font-size: 2.75rem;
            background:
                radial-gradient(90% 80% at 70% 20%, rgba(227, 27, 35, .30) 0%, transparent 62%),
                linear-gradient(140deg, #17233A 0%, #0C1626 100%);
        }

        .welcome__row { padding: 22px 24px 26px; }

        .welcome__row h3 { margin: 0; font-size: 1.125rem; font-weight: 650; letter-spacing: -0.015em; }
        .welcome__row p  { margin: 9px 0 0; color: var(--muted); font-size: .9375rem; line-height: 1.6; }

        @media (max-width: 720px) { .welcome__list { grid-template-columns: 1fr; } }

        /* ----------------------------------------------------------- jobs */
        .jobs__bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            padding-bottom: 22px;
            margin-bottom: 8px;
            border-bottom: 1px solid var(--line);
        }

        .jobs__count { margin-right: auto; font-size: .9375rem; color: var(--muted); }
        .jobs__count b { color: var(--ink); }

        .jobs__bar select {
            height: 42px;
            padding: 0 36px 0 14px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: #fff url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%235A6A80' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 12px center / 16px;
            color: var(--ink);
            font: inherit;
            font-size: .9375rem;
            appearance: none;
            cursor: pointer;
        }

        .jobs__clear {
            border: 0;
            background: none;
            color: var(--brand);
            font: inherit;
            font-size: .9375rem;
            font-weight: 600;
            cursor: pointer;
            padding: 0 4px;
        }

        /* A list, not a grid of cards: job titles are scanned down a column,
           and a row gives the title far more room than a card ever does. */
        .job {
            display: flex;
            align-items: center;
            gap: 28px;
            padding: 30px 4px;
            border-bottom: 1px solid var(--line);
            transition: background-color .12s ease, padding-left .12s ease;
        }

        .job:hover { background: var(--wash); padding-left: 16px; }

        .job__body { flex: 1; min-width: 0; }

        .job__title {
            margin: 0;
            font-size: 1.375rem;
            font-weight: 650;
            letter-spacing: -0.015em;
            color: var(--ink);
        }

        .job__meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 16px;
            margin-top: 10px;
            font-size: .875rem;
            color: var(--muted);
        }

        .job__tag {
            display: inline-flex;
            align-items: center;
            padding: 3px 11px;
            border-radius: 999px;
            background: #FDECEC;
            color: #A3131A;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .job__desc {
            margin: 12px 0 0;
            max-width: 62ch;
            color: var(--muted);
            font-size: .9375rem;
            line-height: 1.6;
        }

        .job__apply {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            height: 46px;
            padding: 0 26px;
            border-radius: 6px;
            border: 1.5px solid var(--ink);
            color: var(--ink);
            font-size: .9375rem;
            font-weight: 650;
            transition: background-color .15s ease, color .15s ease, border-color .15s ease;
        }

        .job__apply:hover { background: var(--brand); border-color: var(--brand); color: #fff; }
        .job__apply i { transition: transform .15s ease; }
        .job:hover .job__apply i { transform: translateX(3px); }

        .jobs__empty {
            padding: 72px 0;
            text-align: center;
            color: var(--muted);
        }

        .jobs__empty i { font-size: 2rem; color: #C3CDDC; margin-bottom: 16px; display: block; }

        /* ------------------------------------------------------- benefits */
        /* Fixed counts rather than auto-fit: there are six of these, and six
           divides by three and by two but not by four - auto-fit picked four
           and left two empty grey cells sitting there like holes. */
        .benefits {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--line);
            border: 1px solid var(--line);
        }

        /* Three of them now, so it goes straight to one column: two would
           leave a single empty cell, and this grid shows its gaps as grey. */
        @media (max-width: 900px) { .benefits { grid-template-columns: 1fr; } }

        .benefits--after { margin-top: 56px; }

        /* ------------------------------------------------------- the bento */
        /* Twelve columns, tiles of different spans. Uniform cards read as a
           spreadsheet; mixing a solid colour block, a photograph and text at
           different widths is what makes a page look composed. */
        .bento {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 16px;
        }

        .bento--after { margin-top: 16px; }

        .bento__eyebrow {
            display: block;
            font-size: .6875rem;
            font-weight: 700;
            letter-spacing: .15em;
            text-transform: uppercase;
            margin-bottom: 14px;
            opacity: .85;
        }

        .bento__eyebrow--red { color: var(--brand); opacity: 1; }

        /* The one solid block of brand colour on the page. It carries the
           claim; everything around it carries the evidence. */
        .bento__red {
            grid-column: span 5;
            background: var(--brand);
            color: #fff;
            border-radius: 16px;
            padding: 34px 36px 34px;
            display: flex;
            flex-direction: column;
            /* Eyebrow pinned top, the claim sitting at the foot - the gap
               between them is what gives the tile its weight. Centring all
               three made it read as a caption block. */
            justify-content: space-between;
            min-height: 19rem;
        }

        .bento__red h3 {
            margin: auto 0 0;
            font-family: var(--font-head, 'Space Grotesk', system-ui, sans-serif);
            font-size: clamp(1.75rem, 3.1vw, 2.5rem);
            font-weight: 700;
            line-height: 1.12;
            letter-spacing: -0.025em;
        }

        .bento__red p { margin: 16px 0 0; font-size: .9375rem; line-height: 1.6; color: rgba(255,255,255,.88); }

        .bento__photo {
            grid-column: span 7;
            position: relative;
            min-height: 19rem;
            border-radius: 16px;
            overflow: hidden;
            background: var(--ink) center/cover;
            display: flex;
            align-items: flex-end;
        }

        .bento__photo-cap {
            width: 100%;
            padding: 60px 30px 26px;
            background: linear-gradient(to top, rgba(12,22,38,.92), transparent);
            color: #fff;
        }

        .bento__photo-cap .bento__eyebrow { color: #FF6B72; opacity: 1; margin-bottom: 8px; }
        .bento__photo-cap p { margin: 0; font-size: 1.25rem; font-weight: 650; letter-spacing: -0.015em; }

        /* On its own line beneath the words, as on theirs - an arrow tucked
           after the full stop reads as punctuation. */
        .bento__photo-cap i { display: block; margin-top: 10px; font-size: .9375rem; }

        .bento__cell {
            grid-column: span 4;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 30px 28px 32px;
        }

        .bento__cell--span6 { grid-column: span 6; }

        /* A short red rule across the top edge, the way their cards are marked.
           Drawn rather than a border so it stops short of the corners. */
        .bento__cell { position: relative; overflow: hidden; }

        .bento__cell::before {
            content: "";
            position: absolute;
            top: 0;
            left: 28px;
            width: 62px;
            height: 3px;
            background: var(--brand);
        }

        .bento__cell h3 { margin: 0; font-size: 1.125rem; font-weight: 650; letter-spacing: -0.015em; }
        .bento__cell p  { margin: 10px 0 0; color: var(--muted); font-size: .9375rem; line-height: 1.65; }

        /* ------------------------------------------- the three detail lists */
        .pack {
            grid-column: span 4;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 30px 28px 32px;
        }

        .pack__head { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }

        .pack__icon {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 11px;
            background: var(--ink);
            color: #fff;
        }

        .pack__head h3 { margin: 0; font-size: 1.125rem; font-weight: 650; letter-spacing: -0.015em; }

        .pack__list { list-style: none; margin: 0; padding: 0; }

        .pack__list li {
            display: flex;
            gap: 12px;
            padding: 14px 0;
            border-top: 1px solid var(--line);
        }

        .pack__list li:first-child { border-top: 0; padding-top: 0; }

        .pack__list li > i {
            flex-shrink: 0;
            margin-top: 3px;
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #FDECEC;
            color: var(--brand);
            font-size: .5625rem;
        }

        .pack__list b    { display: block; font-size: .9375rem; font-weight: 650; letter-spacing: -0.01em; }
        .pack__list span { display: block; margin-top: 4px; color: var(--muted); font-size: .875rem; line-height: 1.55; }

        .pack__note { margin: 0; color: var(--muted); font-size: .9375rem; line-height: 1.6; }

        @media (max-width: 1040px) {
            .bento__red, .bento__photo { grid-column: span 12; }
            .pack, .bento__cell { grid-column: span 6; }
        }

        @media (max-width: 700px) {
            .pack, .bento__cell { grid-column: span 12; }
            .bento__red { padding: 30px 26px 32px; }
            .bento__photo { min-height: 15rem; }
        }

        /* ------------------------------------------------- statutory leave */
        .lawful {
            margin-top: 24px;
            padding: 26px 28px 24px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
        }

        .lawful h3 {
            margin: 0 0 16px;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .lawful ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .lawful li {
            padding: 7px 14px;
            border-radius: 999px;
            background: var(--wash);
            border: 1px solid var(--line);
            font-size: .875rem;
            font-weight: 600;
        }

        .lawful p { margin: 16px 0 0; color: var(--muted); font-size: .875rem; line-height: 1.6; }

        .packs__foot {
            margin: 26px 0 0;
            max-width: 62ch;
            color: var(--muted);
            font-size: .875rem;
            line-height: 1.65;
        }

        @media (max-width: 1080px) { .packs { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 720px)  { .packs { grid-template-columns: 1fr; } }

        .benefit { background: #fff; padding: 36px 32px 38px; }

        .benefit__icon {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #FDECEC;
            color: var(--brand);
            margin-bottom: 22px;
        }

        .benefit__eyebrow {
            display: block;
            font-size: .6875rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .benefit h3 { margin: 0; font-size: 1.1875rem; font-weight: 650; letter-spacing: -0.015em; }
        .benefit p { margin: 10px 0 0; color: var(--muted); font-size: .9375rem; line-height: 1.65; }

        /* ----------------------------------------------------------- team */
        .team { overflow: hidden; }

        .team__rail {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            padding: 4px 32px 18px;
            /* proximity, not mandatory: mandatory snapping combined with the
               rail's own left padding meant scrollLeft could never reach 0, so
               the "previous" button had no way to know it was at the start. */
            scroll-snap-type: x proximity;
            -webkit-overflow-scrolling: touch;
        }

        /* The buttons are the affordance now, so the bar itself goes. */
        .team__rail { scrollbar-width: none; scroll-behavior: smooth; }
        .team__rail::-webkit-scrollbar { display: none; }

        .team__controls { display: flex; gap: 10px; margin-top: 20px; }

        .team__arrow {
            width: 46px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: 1.5px solid var(--ink);
            background: #fff;
            color: var(--ink);
            cursor: pointer;
            font-size: .9375rem;
            transition: background-color .15s ease, color .15s ease, opacity .15s ease;
        }

        .team__arrow:hover:not(:disabled) { background: var(--brand); border-color: var(--brand); color: #fff; }

        /* Disabled rather than removed: a control that disappears at the end of
           the strip makes the row jump under the cursor. */
        .team__arrow:disabled { opacity: .28; cursor: default; }

        .team__shot {
            flex: 0 0 auto;
            width: 15rem;
            aspect-ratio: 3 / 4;
            border-radius: 12px;
            background-size: cover;
            background-position: center top;
            background-color: var(--ink);
            scroll-snap-align: start;
        }

        /* The group shots are landscape; forcing them into the portrait tile
           would cut the people on both ends out of their own photograph. */
        .team__shot--wide { width: 26.5rem; aspect-ratio: 16 / 9; background-position: center; }

        @media (max-width: 860px) {
            .team__rail { padding-left: 20px; padding-right: 20px; }
            .team__shot { width: 11.5rem; }
            .team__shot--wide { width: 20rem; }
        }

        /* --------------------------------------------------- workstations */
        /* 15rem, not 13: at this page width that settles on four columns, and
           the twelve tiles divide evenly by four, three, two and one - so no
           breakpoint leaves a ragged half-row hanging. */
        .stations {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
            gap: 14px;
        }

        .station {
            margin: 0;
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            background: var(--ink);
        }

        .station img {
            display: block;
            width: 100%;
            aspect-ratio: 3 / 2;
            object-fit: cover;
            transition: transform .3s ease;
        }

        .station:hover img { transform: scale(1.05); }

        .station figcaption {
            position: absolute;
            inset: auto 0 0 0;
            padding: 24px 12px 10px;
            background: linear-gradient(to top, rgba(12, 22, 38, .93), transparent);
            color: #fff;
            font-size: .8125rem;
            font-weight: 600;
            line-height: 1.3;
        }

        .stations__more {
            margin: 18px 0 0;
            color: var(--muted);
            font-size: .9375rem;
        }

        /* ---------------------------------------------------------- video */
        .clip {
            border-radius: 14px;
            overflow: hidden;
            background: var(--ink);
            border: 1px solid var(--line);
        }

        .clip__video { display: block; width: 100%; aspect-ratio: 16 / 9; object-fit: cover; background: #000; }

        .clip__empty {
            aspect-ratio: 16 / 9;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            background:
                radial-gradient(90% 90% at 50% 35%, rgba(227, 27, 35, .34) 0%, transparent 62%),
                linear-gradient(140deg, #17233A 0%, #0C1626 100%);
        }

        .clip__empty i { font-size: 3rem; color: rgba(255, 255, 255, .30); }
        .clip__empty p { margin: 0; color: rgba(255, 255, 255, .55); font-size: .9375rem; }

        /* ------------------------------------------------------------ cta */
        .cta {
            position: relative;
            isolation: isolate;
            padding: 112px 0;
            background: var(--ink);
            overflow: hidden;
        }

        .cta__img { position: absolute; inset: 0; z-index: -2; background-size: cover; background-position: center; }

        .cta__img--empty {
            background:
                radial-gradient(100% 120% at 20% 100%, rgba(227, 27, 35, .40) 0%, transparent 60%),
                linear-gradient(120deg, #0C1626 0%, #17233A 100%);
        }

        .cta::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background: rgba(12, 22, 38, .72);
        }

        .cta h2 { color: #fff; font-size: clamp(2.25rem, 5vw, 3.75rem); max-width: 16ch; }
        .cta p { margin: 20px 0 0; color: #C3CDDC; font-size: 1.0625rem; max-width: 44ch; line-height: 1.6; }

        .cta__btn {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-top: 38px;
            height: 58px;
            padding: 0 36px;
            border-radius: 6px;
            background: var(--brand);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            transition: background-color .15s ease, transform .15s ease;
        }

        .cta__btn:hover { background: #B5141A; transform: translateY(-2px); }

        /* --------------------------------------------------------- footer */
        .careers__foot {
            padding: 40px 0;
            border-top: 1px solid var(--line);
            display: flex;
            flex-wrap: wrap;
            gap: 10px 28px;
            align-items: center;
            font-size: .875rem;
            color: var(--muted);
        }

        .careers__foot a { color: var(--ink); font-weight: 600; }
        .careers__foot a:hover { color: var(--brand); }

        @media (max-width: 860px) {
            .careers__wrap { padding: 0 20px; }
            .hero { min-height: 0; padding: 88px 0 48px; }
            .section { padding: 68px 0; }
            .cta { padding: 76px 0; }
            .search__go { flex: 1 1 100%; }

            /* The apply button drops under the title rather than squeezing it
               into two words per line. */
            .job { flex-direction: column; align-items: flex-start; gap: 18px; }
            .job__apply { width: 100%; justify-content: center; }
        }

        /* ------------------------------------------ pages without a hero */
        /* Every page but the home page starts on white, so it has to begin
           below the fixed bar rather than underneath it. */
        .careers__below-bar { padding-top: 82px; }

        @media (max-width: 720px) { .careers__below-bar { padding-top: 68px; } }

        /* The search on a white ground needs the border the hero's does not. */
        .search--light input { border: 1px solid var(--line); }
        .search--light { margin-top: 4px; }

        /* ------------------------------------------- the home page's doors */
        /* Two across, not four. At four the card was 327px wide and the
           photograph in it was the size of a thumbnail - too small to see who
           was in it, and asking the browser to shrink a photograph that far is
           what made them look coarse. */
        .paths {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 22px;
        }

        .path {
            display: flex;
            flex-direction: column;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .path:hover { transform: translateY(-3px); box-shadow: 0 14px 30px rgba(12, 22, 38, .10); }

        .path__pic {
            aspect-ratio: 4 / 3;
            background-size: cover;
            background-position: center;
            background-color: var(--ink);
        }

        .path__pic--empty {
            background:
                radial-gradient(90% 80% at 70% 20%, rgba(227, 27, 35, .30) 0%, transparent 62%),
                linear-gradient(140deg, #17233A 0%, #0C1626 100%);
        }

        .path__body { padding: 22px 22px 24px; display: flex; flex-direction: column; flex: 1; }
        .path__body h3 { margin: 0; font-size: 1.0625rem; font-weight: 650; letter-spacing: -0.015em; }
        .path__body p  { margin: 9px 0 0; flex: 1; color: var(--muted); font-size: .9375rem; line-height: 1.6; }

        .path__go { margin-top: 16px; color: var(--brand); font-size: .875rem; font-weight: 700; }
        .path__go i { margin-left: 7px; transition: transform .15s ease; }
        .path:hover .path__go i { transform: translateX(3px); }

        @media (max-width: 620px)  { .paths { grid-template-columns: 1fr; } }

        /* ------------------------------------------------- who we are */
        /* Shorter than the home page's, which has a search box to hold. */
        .hero--story { min-height: 0; padding: 150px 0 72px; }
        .hero--story .careers__display { font-size: clamp(2.25rem, 5.4vw, 4rem); }

        /* The bar is 68px on a phone rather than 82, so the same 150px of
           headroom leaves a gap out of proportion to the type. */
        @media (max-width: 720px) { .hero--story { padding: 112px 0 52px; } }

        .story__detail {
            position: relative;
            margin-top: 28px;
            min-height: 17rem;
            border-radius: 16px;
            overflow: hidden;
            background-size: cover;
            background-position: center;
            background-color: var(--ink);
            display: flex;
            align-items: flex-end;
        }

        @media (max-width: 860px) { .story__band { height: 14rem; } }

        .figures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--line);
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
        }

        .figure { background: #fff; padding: 30px 24px; text-align: center; }

        .figure b {
            display: block;
            font-size: 2.75rem;
            font-weight: 700;
            line-height: 1;
            letter-spacing: -0.04em;
            color: var(--brand);
        }

        .figure span { display: block; margin-top: 10px; color: var(--muted); font-size: .875rem; }

        .floor { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }

        .floor__card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .floor__pic { aspect-ratio: 3 / 2; background-size: cover; background-position: center; background-color: var(--ink); }

        .floor__pic--empty {
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,.22);
            font-size: 2rem;
            background:
                radial-gradient(90% 80% at 70% 20%, rgba(227,27,35,.32) 0%, transparent 60%),
                linear-gradient(140deg, #17233A 0%, #0C1626 100%);
        }

        .floor__body { padding: 20px 22px 24px; }
        .floor__body h3 { margin: 0; font-size: 1.0625rem; font-weight: 650; letter-spacing: -0.015em; }
        .floor__body p  { margin: 8px 0 0; color: var(--muted); font-size: .9375rem; line-height: 1.6; }

        @media (max-width: 1000px) { .floor { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 700px)  { .floor, .figures { grid-template-columns: 1fr; } }

        /* ---------------------------------------------------------- explore */
        .explore__head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px 40px;
            margin-bottom: 34px;
        }

        .explore__note { margin: 0; max-width: 34rem; color: var(--muted); font-size: .9375rem; line-height: 1.65; }

        .explore { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }

        .explore__big {
            position: relative;
            isolation: isolate;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 25rem;
            padding: 26px 28px 28px;
            border-radius: 16px;
            overflow: hidden;
            background: var(--ink) center/cover;
            color: #fff;
        }

        /* A scrim, or the words land on whatever the photograph is doing. */
        .explore__big::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background: linear-gradient(to top, rgba(12,22,38,.94) 6%, rgba(12,22,38,.55) 46%, rgba(12,22,38,.28) 100%);
        }

        .explore__big--rule::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--brand);
            z-index: 1;
        }

        .explore__chip {
            width: 46px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: rgba(255, 255, 255, .16);
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, .26);
            font-size: 1rem;
        }

        .explore__big-body .bento__eyebrow { color: rgba(255, 255, 255, .85); margin-bottom: 8px; }

        .explore__big-body h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .explore__big-body p { margin: 10px 0 0; max-width: 42ch; color: rgba(255,255,255,.82); font-size: .9375rem; line-height: 1.6; }

        .explore__link { display: inline-block; margin-top: 18px; font-size: .9375rem; font-weight: 700; }
        .explore__link i { margin-left: 8px; transition: transform .15s ease; }
        .explore__big:hover .explore__link i { transform: translateX(4px); }

        /* ------------------------------------------------- the small tiles */
        .explore__small {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-top: 18px;
        }

        .explore__tile {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 18px 20px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            transition: border-color .15s ease, transform .15s ease;
        }

        .explore__tile:hover { border-color: #F3C7C9; transform: translateY(-2px); }

        .explore__tile-icon {
            flex-shrink: 0;
            width: 46px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #FDECEC;
            color: var(--brand);
        }

        .explore__tile-text { flex: 1; min-width: 0; }
        .explore__tile-text .bento__eyebrow { margin-bottom: 4px; }
        .explore__tile-text b { display: block; font-size: .9375rem; font-weight: 650; color: var(--ink); letter-spacing: -0.01em; }

        .explore__tile-go { flex-shrink: 0; color: var(--muted); font-size: .8125rem; }

        @media (max-width: 900px) {
            .explore, .explore__small { grid-template-columns: 1fr; }
            .explore__big { min-height: 20rem; }
        }

        /* ----------------------------------------------------------- voices */
        .voices { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }

        .voice {
            position: relative;
            isolation: isolate;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            aspect-ratio: 3 / 4;
            padding: 18px;
            border-radius: 14px;
            overflow: hidden;
            background: var(--ink) center/cover;
            color: #fff;
        }

        /* Only over the lower half, so the face stays clear of it. */
        .voice::after {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -1;
            background: linear-gradient(to top, rgba(12,22,38,.92) 4%, rgba(12,22,38,.55) 38%, transparent 62%);
        }

        .voice__badge {
            align-self: flex-start;
            padding: 6px 13px;
            border-radius: 999px;
            background: var(--brand);
            color: #fff;
            font-size: .6875rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        /* Pushed to the foot whether or not a badge sits above it. */
        .voice__words { margin-top: auto; }

        .voice__words blockquote {
            margin: 0;
            font-size: 1.0625rem;
            font-weight: 600;
            line-height: 1.45;
            letter-spacing: -0.01em;
        }

        .voice__name { display: block; margin-top: 12px; color: rgba(255,255,255,.78); font-size: .875rem; }

        @media (max-width: 900px) { .voices { grid-template-columns: 1fr; } }

        /* ---------------------------------------------------------- founder */
        .founder { display: grid; grid-template-columns: 26rem 1fr; gap: 40px; align-items: start; }

        .founder__pic {
            aspect-ratio: 4 / 5;
            border-radius: 16px;
            background-size: cover;
            background-position: center;
            background-color: var(--ink);
        }

        .founder__pic--empty {
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, .26);
            font-size: 3rem;
            background:
                radial-gradient(90% 80% at 70% 20%, rgba(227, 27, 35, .32) 0%, transparent 62%),
                linear-gradient(140deg, #17233A 0%, #0C1626 100%);
        }

        /* Name and role above the words, as a label - theirs reads
           "Tadashi Yanai - Chairman, President & CEO". */
        .founder__who {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: baseline;
            padding-bottom: 16px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--line);
        }

        .founder__who b { font-size: .9375rem; font-weight: 700; }
        .founder__who span { color: var(--muted); font-size: .8125rem; text-transform: uppercase; letter-spacing: .09em; }

        /* The one line to take away, set large. */
        .founder__headline {
            margin: 0 0 20px;
            font-family: var(--font-head, 'Space Grotesk', system-ui, sans-serif);
            font-size: clamp(1.375rem, 2.4vw, 1.875rem);
            font-weight: 700;
            line-height: 1.24;
            letter-spacing: -0.025em;
        }

        .founder__body blockquote {
            margin: 0;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.7;
            white-space: pre-line;
        }

        @media (max-width: 860px) {
            .founder { grid-template-columns: 1fr; gap: 24px; }
            .founder__pic { aspect-ratio: 16 / 10; }
        }

        /* ---------------------------------------------------------- day one */
        .dayone { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }

        .dayone__card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 26px 24px 28px;
        }

        .dayone__icon {
            width: 46px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #FDECEC;
            color: var(--brand);
            margin-bottom: 18px;
        }

        .dayone__card h3 { margin: 0; font-size: 1.0625rem; font-weight: 650; letter-spacing: -0.015em; }
        .dayone__card p  { margin: 9px 0 0; color: var(--muted); font-size: .9375rem; line-height: 1.6; }

        @media (max-width: 1040px) { .dayone { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 620px)  { .dayone { grid-template-columns: 1fr; } }

        /* --------------------------------------------------- job pathways */
        /* Uniqlo's jobs page opens on three of these: a photograph, the name of
           the pathway, and a live count. The count is the point - it tells you
           whether the door is worth opening before you open it. */
        .mosaic {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .mosaic__card {
            position: relative;
            display: block;
            aspect-ratio: 4 / 3;
            border-radius: 14px;
            overflow: hidden;
            background: var(--ink);
            color: #fff;
        }

        .mosaic__img {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            transition: transform .4s ease;
        }

        .mosaic__img--empty {
            background:
                radial-gradient(90% 80% at 70% 20%, rgba(227, 27, 35, .34) 0%, transparent 62%),
                linear-gradient(140deg, #17233A 0%, #0C1626 100%);
        }

        .mosaic__card:hover .mosaic__img { transform: scale(1.05); }

        /* The label sits on a scrim of its own rather than on the photograph,
           which on a dark shop floor is the difference between a readable
           count and a guess. */
        .mosaic__label {
            position: absolute;
            inset: auto 0 0 0;
            display: flex;
            align-items: flex-end;
            gap: 14px;
            padding: 46px 22px 20px;
            background: linear-gradient(to top, rgba(12, 22, 38, .95) 10%, rgba(12, 22, 38, .70) 52%, transparent 100%);
        }

        .mosaic__text { flex: 1; min-width: 0; }

        .mosaic__text b {
            display: block;
            font-family: var(--font-head, 'Space Grotesk', system-ui, sans-serif);
            font-size: 1.375rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .mosaic__count { display: block; margin-top: 6px; color: rgba(255, 255, 255, .82); font-size: .875rem; font-weight: 600; }
        .mosaic__note  { display: block; margin-top: 3px; color: rgba(255, 255, 255, .62); font-size: .8125rem; }

        .mosaic__go {
            flex-shrink: 0;
            margin-bottom: 4px;
            color: #fff;
            font-size: .875rem;
            transition: transform .15s ease;
        }

        .mosaic__card:hover .mosaic__go { transform: translateX(4px); }

        /* Two pathways should be two wide cards, not two thirds of a row with a
           hole where the third would be. Declared before the media queries so
           a narrow screen still wins. */
        .mosaic--two { grid-template-columns: repeat(2, 1fr); }

        @media (max-width: 1000px) { .mosaic { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px)  { .mosaic { grid-template-columns: 1fr; } }

        /* The jobs hero is shorter than the home page's - the pathway cards
           below it are the point of the page and should not be a scroll away. */
        .hero--jobs { min-height: 0; padding: 150px 0 66px; }
        .hero--jobs .careers__display { font-size: clamp(2.25rem, 5.4vw, 4rem); }

        @media (max-width: 720px) { .hero--jobs { padding: 112px 0 48px; } }

        /* ------------------------------------------------- hover on pictures */
        /* Everything with a photograph in it answers to the pointer the same
           way: pictures zoom a little inside their frame, cards that carry
           words lift instead. Heroes are deliberately excluded - a hero is
           the size of the screen and the headline sits on top of it. */
        .floor__pic,
        .welcome__pic,
        .path__pic,
        .bento__photo,
        .event__hero,
        .team__shot,
        .founder__pic,
        .story__detail {
            transition: transform .4s ease;
        }

        /* These sit inside something that clips, so the picture grows and the
           frame does not. */
        .floor__card:hover .floor__pic,
        .welcome__item:hover .welcome__pic,
        .path:hover .path__pic,
        .bento__photo:hover,
        .event__hero:hover {
            transform: scale(1.05);
        }

        /* These are their own frame, so a smaller move: enough to answer the
           pointer, not enough to collide with the tile beside them. */
        .team__shot:hover,
        .founder__pic:hover,
        .story__detail:hover {
            transform: scale(1.02);
        }

        /* Cards with words on the photograph lift rather than zoom. */
        .voice,
        .explore__big {
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .voice:hover,
        .explore__big:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 34px rgba(12, 22, 38, .22);
        }

        /* A pointer, so the whole tile reads as one thing you can act on. */
        .floor__card, .welcome__item, .voice, .team__shot, .event__hero,
        .bento__photo, .story__detail { cursor: default; }

        /* Somebody who has asked their system not to animate gets none of it. */
        @media (prefers-reduced-motion: reduce) {
            .floor__pic, .welcome__pic, .path__pic, .bento__photo, .event__hero,
            .team__shot, .founder__pic, .story__detail, .voice, .explore__big,
            .station img, .face img, .mosaic__img {
                transition: none;
            }

            .floor__card:hover .floor__pic, .welcome__item:hover .welcome__pic,
            .path:hover .path__pic, .bento__photo:hover, .event__hero:hover,
            .team__shot:hover, .founder__pic:hover, .story__detail:hover,
            .station:hover img, .face:hover img, .mosaic__card:hover .mosaic__img {
                transform: none;
            }

            .voice:hover, .explore__big:hover { transform: none; }
        }

        /* ---------------------------------------------------------- events */
        .event + .event { margin-top: 56px; padding-top: 48px; border-top: 1px solid var(--line); }

        .event__head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px 40px;
            margin-bottom: 24px;
        }

        .event__when {
            display: block;
            margin-bottom: 8px;
            color: var(--brand);
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .event__head h3 {
            margin: 0;
            font-family: var(--font-head, 'Space Grotesk', system-ui, sans-serif);
            font-size: clamp(1.5rem, 3vw, 2.125rem);
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        .event__head p { margin: 0; max-width: 42rem; color: var(--muted); font-size: .9375rem; line-height: 1.65; }

        /* The wide frame leads; the rest are tiles under it. */
        .event__hero {
            position: relative;
            margin: 0;
            aspect-ratio: 16 / 9;
            border-radius: 16px;
            overflow: hidden;
            background: var(--ink) center/cover;
        }

        .event__hero figcaption {
            position: absolute;
            inset: auto 0 0 0;
            padding: 46px 22px 18px;
            background: linear-gradient(to top, rgba(12, 22, 38, .92), transparent);
            color: #fff;
            font-size: .9375rem;
            font-weight: 600;
        }

        .event__grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-top: 14px;
        }

        @media (max-width: 900px) { .event__grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 560px) { .event__grid { grid-template-columns: 1fr; } }

        /* ------------------------------------------------------------ faces */
        .faces { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }

        .face { margin: 0; position: relative; border-radius: 14px; overflow: hidden; background: var(--ink); }

        .face img {
            display: block;
            width: 100%;
            aspect-ratio: 3 / 4;
            object-fit: cover;
            transition: transform .3s ease;
        }

        .face:hover img { transform: scale(1.04); }

        .face figcaption {
            position: absolute;
            inset: auto 0 0 0;
            padding: 30px 14px 12px;
            background: linear-gradient(to top, rgba(12, 22, 38, .92), transparent);
            color: #fff;
            font-size: .875rem;
            font-weight: 600;
        }

        @media (max-width: 860px) { .faces { grid-template-columns: 1fr; } }
    </style>







