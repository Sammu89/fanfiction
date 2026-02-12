### **Specification Document for Transition from Database to localStorage Approach**

---

#### **1. Overview**

This document outlines the new approach for storing and synchronizing user interactions (likes, ratings, views) on the plugin. The current system stores interaction data in a database tied to user IPs. This approach will be replaced by a **localStorage-based system**, which will save interaction data in the user's browser and only sync it with the server upon login. This document defines the data structure, interactions, and synchronization logic to replace the old IP-based database system.

#### **2. Key Concepts**

* **localStorage**: We will store interaction data (like/dislike/ratings/views) per story and chapter locally in the user's browser using localStorage.
* **Database Syncing**: When a user logs in, any interaction data in localStorage will be compared with data in the database. Missing or new information will be synced between the two.
* **Interaction Types**:

  * **Like**: User likes a chapter.
  * **Dislike**: User dislikes a chapter.
  * **View/Read**: User has read/viewed the chapter (view and read are considered equivalent).
  * **Rating**: User has rated a chapter (1–5 stars).

#### **3. Data Structure for localStorage**

For each story and chapter, the following data will be saved in **localStorage**:

1. **Like** or **Dislike**: These are mutually exclusive, meaning if a user likes a chapter, the dislike is deactivated, and vice versa.
2. **Rating**: This is a numerical value (1–5) indicating the user’s rating for a chapter.
3. **View**: This indicates that the user has read the chapter (equivalent to "view").
4. **Timestamp**: The timestamp of the last action (like, dislike, rating, or view). This helps sync and track the most recent interaction.

**Example localStorage entry for chapter interactions**:

```json
{
  "story_123_chapter_456": {
    "like": true,       // true if the user liked the chapter
   // if no dislike flag existgs, meaning the user did not dislike the chapter, save logic for like
    "rating": 4,        // rating from 1-5
// no view flag, the simple fact that the entry exists, means the chapter was viewed
    "timestamp": 1618605000000  // timestamp of the last action
  }
}
```

**Note**: For every chapter, only **one of `like` or `dislike`** will be true, and `view` will always be true if the chapter has been viewed.

---

#### **4. User Actions and Behavior**

* **Like/Dislike**:

  * A user can either **like** or **dislike** a chapter, but **not both**. If the user likes a chapter, the dislike option is automatically turned off, and vice versa.
  * Whenever a like or dislike action is triggered, the **timestamp** for that chapter is updated.

* **Rating**:

  * The user can rate a chapter between **1 and 5 stars**. This is stored in the `rating` field.
  * The **timestamp** of the rating is updated whenever the user changes their rating.

* **View/Read**:

  * Viewing a chapter is the same as reading it. The system saves the `view` flag as **true** if the chapter is read.
  * The **timestamp** is updated every time the user views a chapter.

#### **5. Syncing Data Between localStorage and Database**

##### **When User is Logged In:**

While the user is logged in, any action taken (like, dislike, rating, view) will be stored in **both the database and localStorage** at the same time. The following process will occur:

1. **Store data in localStorage**: As the user interacts with the content (liking, disliking, rating, or viewing a chapter), the relevant data will be saved in **localStorage** immediately.
2. **Store data in the database**: Simultaneously, the same data will be sent to the **database**. This ensures that the database and localStorage always have the most up-to-date interaction information.

##### **When User Logs In on a New Browser or Device**:

When a user logs in on a **new browser or device**, we perform the following actions:

1. **Check data in localStorage**: The system will check **localStorage** for any existing data (likes, dislikes, ratings, views).
2. **Fetch data from the database**: Simultaneously, the system will retrieve the user's interaction data from the **database**.
3. **Compare timestamps**: The system will compare the **timestamps** of the data in localStorage with the database. If the data in the database is newer, it will be updated in **localStorage**. If the data in localStorage is newer, it will be sent to the **database**.
4. **Sync missing or updated information**: Any missing or updated information will be synchronized between the two systems. This ensures that the user has consistent data across devices and browsers.

##### **Sync Process**:

1. Fetch the user's data from the **database**.
2. Loop through each story and chapter, comparing the `timestamp` in the database and localStorage.
3. If the **localStorage timestamp** is newer than the **database timestamp**, update the database.
4. If the **database timestamp** is newer, update **localStorage**.



#### **6. Database Storage for User-Specific Data**

The interaction data (like, dislike, rating, view) will be stored in the **database** on the user's profile:

* **Profile Identification**: The plugin will ensure that this data is tied to the **user’s WordPress account** The data will be saved in a table structure that stores the user’s interactions for each chapter.
* Chapters / Story save the totals (totals of views, likes, dislikes, mean of rating), and it's always in sync with the search index table (so that the ratings, views, likes, dislikes) are correctly stored and in sync



---

#### **7. Summary of Data Flow**

1. **Logged-out Users**: Interaction data (like/dislike, rating, view) is stored **only in localStorage** with a timestamp.
2. **Logged-in Users**: Interaction data is saved in both **localStorage** and the **database** simultaneously. **localStorage** is used for fast access, and the **database** keeps a persistent record.
3. **On Login (New Device/Browsers)**: Data is synced between **localStorage** and the **database**, ensuring consistency between devices. The most recent data is chosen based on the timestamp.

---

#### **8. Conclusion**

By transitioning from a database approach tied to IP addresses to a **localStorage-based approach**, we can:

* Reduce server load by offloading interaction storage to the user's browser.
* Provide a more **seamless and synchronized experience** across different devices and browsers.
* Ensure that data is always up-to-date and consistent by syncing **localStorage** with the **database** when the user logs in.

* User should be able to change / remove their like, dislike and rating!
* We copy the logic from the view data to have summaries like top rated and top liked stories of the week, month, etc
* Absolutely NO legacy code: only one path of logic to Views, Like, Dislike, Ratings on story listing (search list), story view, chapter view, author profile and dashboard.