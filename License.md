Cool — if you’re keeping **exactly those three buckets** (All rights reserved / Public domain / Creative Commons), then your job is basically: **make people choose intent, not legal jargon**.

Here’s how I’d present it to a user who has zero clue what a license is.

## What the user sees (simple picker)

### 1) **All Rights Reserved** (Recommended / Default)

**Label:** “Don’t reuse my story”
**Explanation (1 line):** People can read it here, but they can’t repost, translate, adapt, or sell it without asking you.
**Examples:** “No reposting to other sites. No translations. No audiobooks. No edits.”

---

### 2) **Creative Commons (CC)**

**Label:** “Allow reuse (with rules)”
**Explanation (1 line):** You choose what others are allowed to do with your text (share it, remix it, etc.).
Then you show **three simple toggles** (with normal words), and the site quietly maps it to a CC license.

**Toggles:**

* **Allow reposting / sharing (with credit)** ✅ (always ON in CC, otherwise it’s just All Rights Reserved)
* **Allow modifications (translations, adaptations, remixes)** ✅/❌
  Tooltip: “This includes translating, rewriting, making an audio version, or using it as a base.”
* **Allow commercial use** ✅/❌
  Tooltip (plain):
  “Commercial = someone can make money from it. For example: selling it, putting it behind a paywall, printing it, or monetizing videos/audiobooks with ads/sponsors.”
* **Require ShareAlike** ✅/❌ *(only shown if modifications are allowed)*
  Tooltip: “If someone adapts your work, they must share it under the same rules.”

Under the toggles, show the resulting license **without forcing the user to understand it**:

> “Result: Creative Commons — NonCommercial + NoDerivs (CC BY-NC-ND)”

---

### 3) **Public Domain**

**Label:** “No restrictions (anyone can use it)”
**Explanation (1 line):** Anyone can copy, remix, repost, translate, and even sell it — no permission needed.
**Warning line:** “Only choose this if you truly don’t care how it’s used.”

(If you want to be accurate, call it **CC0** rather than generic “Public Domain”, but UI-wise your label can still say “No restrictions”.)

---

## One *important* fanfiction line (keep it short, not scary)

Put this under the whole section:

> “This controls rights over your original text. It doesn’t give anyone rights over the original fandom/characters.”

That’s enough.

## Default choices (so users don’t face-plant)

* Default = **All Rights Reserved**
* CC expanded = optional “Advanced”
* Add a tiny “Not sure? Pick All Rights Reserved.”

If you want, I can write the **exact microcopy** for the tooltips + the exact mapping table to the 6 CC options Wattpad shows, so your UI behaves identically.


---

Assume **Attribution (credit) is always required** for any CC license you offer (i.e., all your CC results are “CC BY…”, never CC0).

### Toggles

1. **Allow commercial use** (Yes/No)
2. **Allow modifications** (Yes/No)
   *Modifications include translations, adaptations, remixes, podfic/audio, etc.*
3. **Require ShareAlike** (Yes/No)
   Only applies if **Allow modifications = Yes**. If modifications are off, ShareAlike must be disabled.

---

## Full CC mapping table

Let:

* **C** = Allow commercial use
* **D** = Allow modifications (derivatives)
* **SA** = Require ShareAlike

| C (commercial) | D (mods) | SA  | Resulting license |
| -------------- | -------- | --- | ----------------- |
| Yes            | Yes      | No  | **CC BY**         |
| Yes            | Yes      | Yes | **CC BY-SA**      |
| No             | Yes      | No  | **CC BY-NC**      |
| No             | Yes      | Yes | **CC BY-NC-SA**   |
| Yes            | No       | —   | **CC BY-ND**      |
| No             | No       | —   | **CC BY-NC-ND**   |

That’s the full set Wattpad shows under CC (plus the generic “Attribution Creative Commons (CC)” which is basically **CC BY**).

**Important logic constraints (so you don’t generate nonsense):**

* If **D = No**, force **SA = Off/hidden** (because ShareAlike only makes sense if derivatives exist).
* If user toggles **SA = On**, force **D = Yes** (or just auto-enable D).

---

## Your 3 top-level choices (as you requested)

You said you want:

* All Rights Reserved
* Public Domain
* Creative Commons

That’s totally fine. Just implement them like this:

### A) All Rights Reserved

No CC toggles at all.

### B) Public Domain (best implemented as **CC0**)

Public Domain isn’t always something an author can “declare” in every jurisdiction, but **CC0** is the standard tool that expresses “no rights reserved”. In UI you can label it “Public domain / CC0”.

### C) Creative Commons

Show the 3 toggles above, and display the resulting CC license name.

---

### Result line (always visible under CC toggles)

**“Resulting license:”** CC BY / CC BY-SA / CC BY-NC / CC BY-NC-SA / CC BY-ND / CC BY-NC-ND
Optional tiny explainer below it:

* **BY** = credit required (always)
* **NC** = no commercial use
* **ND** = no modifications
* **SA** = adaptations must keep same license

---

## Extra: this explications appears as a ALT hover over the licencse on story view. You need also explain Public domain and All Rights Reserved.
You can show the official license code + a friendly description:

* **CC BY** — “Credit required. Reuse + modifications allowed. Commercial allowed.”
* **CC BY-SA** — “Credit required. Modifications allowed. Must keep same license. Commercial allowed.”
* **CC BY-NC** — “Credit required. Modifications allowed. Non-commercial only.”
* **CC BY-NC-SA** — “Credit required. Modifications allowed. Non-commercial only. Must keep same license.”
* **CC BY-ND** — “Credit required. No modifications. Commercial allowed.”
* **CC BY-NC-ND** — “Credit required. No modifications. Non-commercial only.”



