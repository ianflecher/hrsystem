Pictures for the public careers page (/careers).

This folder lives under public/img/ and NOT at public/careers/ on purpose:
a directory whose name matches a route gets served by the web server before
Laravel ever sees the request, which turns /careers into a 404.

Drop a file in here with one of these names and it appears on the page.
Leave it out and that slot falls back to a brand-coloured panel - nothing
breaks, and nothing looks unfinished.

  hero.jpg     the big picture behind the headline   (wide, 2000x1200 or so)
  cta.jpg      the picture behind "Your future starts here"
  voice-1.jpg  photo beside the first quote          (4:3, 800x600 is plenty)
  voice-2.jpg  photo beside the second quote
  voice-3.jpg  photo beside the third quote

.jpg, .jpeg, .png and .webp all work. No code change and no restart needed.

The three quotes themselves are placeholders. Replace the words and names in
resources/views/livewire/careers.blade.php ($voices) with real ones before
showing this page to the public.

Video (optional):

  inside.mp4        a short clip of the floor; .webm also works
  video-poster.jpg  the still shown before it plays

Keep it short and quiet - it plays muted with controls, not autoplaying, so
nobody on mobile data gets ambushed. Under about 5 MB is polite.

-----------------------------------------------------------------------
Where the current pictures came from

Cut down from the camera originals on the multimedia share:
  \ic-multimedia-mike\E\2026\IMPRINT CUSTOMS - EMPLOYEES\
  IMPRINT CUSTOMS SYSTEM - LOGIN PAGE\EDITED

  hero.jpg     <- DSC02289.JPG   (the sewing floor, wide)
  cta.jpg      <- DSC02275.JPG   (the embroidery heads)
  voice-1.jpg  <- DSC02291.JPG   (sewing station)
  voice-2.jpg  <- DSC02322.JPG   (heat press)
  voice-3.jpg  <- DSC02360.png   (office desk)

The originals are 6000px and 10-30 MB each - far too heavy to put on a web
page. These are centre-cropped, resized, saved at quality 80 and stripped of
EXIF, which takes the whole set to about 1 MB. Re-cut from the originals if
you want different framing; do not copy the originals in directly.

WARNING: the three voice photographs show real, identifiable employees, and
the quotes printed beside them are invented placeholder text under the name
"Sample Name". Do not put this page in front of the public until either the
quotes are real and those people have agreed to be quoted, or the photographs
are swapped for ones where nobody is identifiable.

-----------------------------------------------------------------------
The studio portraits (shot 18 Sep)

  voice-1.jpg  <- DSC02398.jpg   testimonial 1
  voice-2.jpg  <- DSC02402.jpg   testimonial 2
  voice-3.jpg  <- DSC02405.jpg   testimonial 3
  team-1..4    <- DSC02398/02400/02402/02405
  team-5, 6    <- DSC02413/02414  (on the floor, not the studio)
  team-group-1 <- DSC02411.jpg   the three of them together
  team-group-2 <- DSC02443.jpg   the group of five

  welcome-part-time.jpg  <- DSC02407.jpg  the group photo
  welcome-fresh-grad.jpg <- DSC02400.jpg  a solo standing portrait

One frame per person in the team strip: several shots of the same face
reads as a mistake rather than a bigger team. The upright portraits are cut
3:4 and anchored near the top of the frame, because a landscape window on a
standing shot crops a person to a band across the chest.

-----------------------------------------------------------------------
Still empty, waiting for you

  welcome-ojt.jpg          beside "OJT and work immersion"
  welcome-no-degree.jpg    beside "No degree needed"

4:3, around 1000x750. Each turns that entry from a plain row into a card
with a photo on top. The two without a file stay as plain rows.

  inside.mp4               the video section
  video-poster.jpg         the still shown before it plays

-----------------------------------------------------------------------
Workstations (station-1 .. station-20)

Every remaining photo from the first shoot, cut 3:2, used on the applicant
company profile at /applicant/inside under "Every workstation".

The captions live in app/Support/Workstations.php, shared by the careers
Inside page and the applicant company profile. They are a plain reading of each photograph and a few are
guesses - if one names the wrong machine or the wrong room, correct the
caption there. The order of the captions matches station-1 .. station-20.

Removing a station-N.jpg drops that tile; the rest shift up.

-----------------------------------------------------------------------
Who we are page

  story-hero.jpg    <- DSC02380.JPG  the wide floor band across the top
  story-detail.jpg  <- DSC02347.JPG  the counter out front

That accounts for every photograph from the first shoot. Swap either file to
change the page; both fall back to a brand panel if removed.

-----------------------------------------------------------------------
Two frames cut so that no page shows the same photograph twice

  bento-photo.jpg    <- DSC02426.jpg  the benefits photo tile
  explore-people.jpg <- DSC02432.jpg  the "our people" card on Who we are

Both replaced slots that were already showing a frame the team carousel or
the six trades cards had on the same page. The rule is: no image twice on
one page. Across pages a photograph may reappear - the Inside page carries
all 23 workstations, so anything using one would otherwise be off limits.

-----------------------------------------------------------------------
Faces of the floor (Inside page) - WAITING FOR YOU

  face-1.jpg   face-2.jpg   face-3.jpg

Three, in one row across. Drop them in and the section appears by itself;
remove them and it disappears again. Portrait crops, 3:4 - roughly 900x1200.

Do NOT reuse a station-*.jpg for these: the workstations grid is on the same
page, and the page would then show the same photograph twice.

To print a name or a station under one, uncomment the line for that number in
CAPTIONS in app/Support/CareersFaces.php, e.g. 1 => 'Joey, marketing'. No
caption is fine - the tile is then just the portrait.

  inside-hero.jpg  <- DSC02273.JPG  the band behind "Step onto the floor."

-----------------------------------------------------------------------
inventory.jpg

The stock room - the inventory desk with the bin racks behind it. Used for
the "Inventory and stock" trade on /careers/who-we-hire and /careers/who-we-are
(App\Support\CareersStory::areas()).

Cut 3:2 from the full-height left of the original so the desk and the racks
are both in frame.

Still wanted: guard.jpg, for the "Security" trade, which draws a fallback
panel until it exists.

-----------------------------------------------------------------------
event-sportsfest-1.jpg .. event-sportsfest-7.jpg

Sportsfest 2026, shown on /careers/who-we-are under "What we get up to"
(App\Support\CareersEvents).

Cut from the share at
  IMPRINT CUSTOMS - EVENTS
    0615.26 - Imprint Sports fest
      0701.26 - SPORTSFEST
        0703.26 - IC SPORTSFEST 2026 PHOTO OUTPUTS
(311 frames; these seven were chosen from a contact sheet of the set).

  1  DSC09818-128  the whole company on the court   16:9, leads the block
  2  RTC00600-30   basketball                       3:2
  3  RTC00145-1    billiards                        3:2
  4  RTC09801-168  Mobile Legends                   3:2
  5  DSC09736-104  cheerdance                       3:2
  6  RTC00755-112  awarding                         3:2
  7  RTC00127-4    out front of the store           3:2

These are identifiable staff on a public page. Anyone clearly recognisable
should have agreed to it, and it is worth checking what else is in frame
before each one goes up.

To add another event: cut its photos as event-<slug>-1.jpg upwards (the
first is the wide one) and add an entry in CareersEvents.

-----------------------------------------------------------------------
front-hero.jpg, front-1.jpg .. front-4.jpg

The Imprint Store on /careers/front, from the September 2026 store shoot at
  IMPRINT CUSTOMS - EMPLOYEES\2026.0920 - LOGIN PAGE SYSTEM\New folder\EDITED
(35 frames; these five chosen from a contact sheet of the set).

  front-hero  DSC02730  the shopfront, 16:9 - it also names Imprint Cafe
                        and Sidepocket, which are the other two sections
                        of that page
  front-1     DSC02699  restocking the racks     3:4
  front-2     DSC02706  helmets and gear         3:4
  front-3     DSC02721  our own apparel          3:4

A different person in each of the three. The shoot follows three people
around the shop, so most frames repeat somebody - the jersey-off-the-rack
frames (DSC02715/02716) are the same person as DSC02706 and were dropped
for that reason.

Three per section, not four: the grid is three across, so three fills the
row and a fourth sits alone underneath. CareersFront looks for -1 .. -3
only, for the store, the barbershop and the cafe alike.

Nobody in the front-* set is looking at the camera - they are people at
work.

The three standing portraits from the same shoot are who-1 .. who-3
(DSC02704, DSC02713, DSC02727, all 3:4), shown on /careers/who-we-hire
under "The people you would join". That section needs all three; with fewer
it is not drawn, because two portraits in a three-wide grid leaves a hole.

who-3 was DSC02726 first, which is the same pose without the smile. The
other two are smiling, so the unsmiling one read as a different kind of
photograph rather than as one of a set.

The tiles are 3:4 portraits because that section renders them in the same
grid as the faces. The originals are 3376x6000, so these are vertical crops
anchored slightly above centre, which keeps faces clear of the caption.

The previous front-hero (DSC02326) and the old front-1/front-2 came from the
earlier shoot and have been replaced.

-----------------------------------------------------------------------
inside.mp4, video-poster.jpg

One minute cut from
  IMPRINT CUSTOMS - EVENTS\EVENTS HIGHLIGHTS FINAL OUTPUT\
  IMPRINT CUSTOMS MILESTONE VIDEO (1920x1440).mp4
which is 6:32, 1920x1440, 120fps, 100 Mbit, 4.7 GB - a master, not a web
file.

Kept: 2:40 - 3:40. Fabric macro, the embroidery head stitching the logo,
the storefront, the Marilaque motorcade, the convoy, the IC-CARES school
visit. It begins on a shot with nobody speaking so the cut does not open
mid-sentence, and both ends are faded (0.5s in, 0.8s out) so it reads as a
clip rather than as something that fell off the end.

  1280x960, 30fps, H.264 crf 24 capped at 2.5 Mbit, AAC 112k,
  faststart, metadata stripped. 18.8 MB.

The poster is frame 0:20 of the cut (the storefront).

To use a different minute: scratch/encode_min.py in the session notes, or
just re-run ffmpeg with another -ss. Everything else in that folder is
client work - Kawasaki, Yamaha, the golf tournaments, Daang Kalikasan -
which is somebody else's footage to publish, so it is not used here.

Two files in that folder are corrupt and cannot be opened at all:
7a0f9a4c-503b-4911-924a-02d7baf1f074.mp4 and
8aecfe02-7edb-4a4e-a31c-4902c41df4a0.mp4 (no moov atom - unfinished
uploads).

-----------------------------------------------------------------------
guard.jpg, barber-2.jpg, barber-3.jpg, explore-people.jpg

From
  IMPRINT CUSTOMS - EMPLOYEES\2026.0920 - LOGIN PAGE SYSTEM\New folder (2)
which is 15 Sony raw frames (.ARW), no JPEGs - developed with rawpy at
camera white balance, default tone curve, nothing else touched.

  guard           DSC02732  3:2   the guard at his desk (confirmed by the
                                  client - he is security, not stockroom). Fills the last
                                  empty card in the ten trades, on
                                  /careers/who-we-hire and /careers/who-we-are
  barber-2        DSC02740  3:4   a cut in progress, seen through the window
  barber-3        DSC02742  3:4   the Twenty One & Co. sign
  explore-people  DSC02744  4:5   the three who run the shop, on the
                                  "team you would join" card

The barbershop frames are all landscape and the tiles are 3:4, so those two
are tight centre crops - 02740 is anchored right of centre to hold the chair,
02742 left of centre to hold the sign.

The store frames in that folder (DSC02743-45) are the same posed group; only
one is used. The shop-floor tiles on /careers/front stay as they are, because
those are deliberately candids and this group is looking at the camera.

Unused: DSC02733/34 (same desk), DSC02735/36 (standing portrait),
DSC02737/38/39/41 (more of the barbershop front), DSC02746 (floor).

-----------------------------------------------------------------------
ONE FRAME, ONE SLOT

Every slot on the site was matched back to its source frame and three
photographs turned out to be doing two jobs each, plus the three studio
portraits which appeared both on the home page and in the team strip:

  DSC02275  was the CTA band and the embroidery card
  DSC02347  was the cafe counter and the "out front" band
  DSC02300  was a workstation tile and the inventory desk
  DSC02398/02399/02404  were voice-1/2/3 and team-1/3/4

Re-cut from frames that were not being used at all:

  cta                DSC02329  the aisle out front
  story-detail       DSC02325  the counter
  station-17         DSC02380  the stockroom
  voice-1            DSC02396  Pau
  voice-2            DSC02403  Ysa
  voice-3            DSC02406  Joey
  team-2             DSC02401
  welcome-part-time  DSC02407  the three of them, eyes open
                               (was DSC02408, which is the same three
                               mid-laugh with all three sets of eyes
                               shut. 02407 is the only frame of this
                               group where everyone is looking at the
                               camera, so the card takes it and the
                               team strip moved to DSC02411.)
  welcome-ojt        DSC02425  the trainee cohort - fills a card that was an
                               icon before
  welcome-no-degree  DSC02734  the guard at his desk - same

voice-1 was briefly DSC02397, which is the same person pulling a face. It
is a good photograph and the wrong one to put a quote about client work
under, so it is not used.

The check is scratch/audit_dupes.py in the session notes: it fingerprints
every source frame at several crops and pairs each slot with its nearest
match, printing anything that pairs twice. Re-run it after adding images.

-----------------------------------------------------------------------
ONE HERO PER PAGE

Every page opens the same way now, as Uniqlo's section pages do: a
photograph, an eyebrow naming the area and the country, the headline over
the picture, a sentence, and one button into the openings.

  /                     hero.jpg         (the sewing floor)
  /careers/who-we-are   story-hero.jpg
  /careers/who-we-hire  who-hero.jpg     DSC02745  three of the shop staff
  /careers/inside       inside-hero.jpg
  /careers/front        front-hero.jpg
  /careers/jobs         jobs-hero.jpg    DSC02269  the transfer line
                                       (was DSC02731, which is the frame
                                       next to front-hero's DSC02730 -
                                       the same shopfront twice, one
                                       slightly tighter, on two heroes.)
  /careers/our-people   people-hero.jpg  DSC02362  the office desks

Jobs and Who we hire were borrowing - Jobs showed the home page's hero and
Who we hire showed the card photograph from Who we are - so both now have
one of their own. Our people had no hero at all.
