document.addEventListener("DOMContentLoaded", function () {
  const eventsContainer = document.getElementById("eventsCalendar");

  if (!eventsContainer) {
    return;
  }

  fetch("api/content.php?type=event")
    .then(function (response) {
      return response.json();
    })
    .then(function (data) {
      if (!data.success || !Array.isArray(data.posts) || data.posts.length === 0) {
        eventsContainer.innerHTML = `
          <article>
            <time>Coming Soon</time>
            <h2>Campaign events will be published soon</h2>
            <p>Check back for town halls, community visits, volunteer trainings and campaign appearances.</p>
          </article>
        `;
        return;
      }

      const events = data.posts
        .filter(function (post) {
          return post.type === "event";
        })
        .sort(function (a, b) {
          return new Date(a.event_date || a.published_at || a.created_at) - new Date(b.event_date || b.published_at || b.created_at);
        });

      eventsContainer.innerHTML = events.map(function (event) {
        const title = escapeHtml(event.title || "Campaign Event");
        const excerpt = escapeHtml(event.excerpt || "");
        const body = escapeHtml(event.body || "");
        const location = escapeHtml(event.event_location || "Venue to be announced");
        const eventDate = event.event_date ? formatEventDate(event.event_date) : "Date to be announced";
        const image = event.featured_image ? escapeHtml(event.featured_image) : "";

        const shareUrl = encodeURIComponent(window.location.origin + "/events.html");
        const shareText = encodeURIComponent(title + " - " + eventDate + " at " + location);

        return `
          <article>
            ${image ? `<img src="${image}" alt="${title}" style="width:100%;max-height:260px;object-fit:cover;border-radius:18px;margin-bottom:18px;">` : ""}
            <time>${eventDate}</time>
            <h2>${title}</h2>
            <p><strong>Venue:</strong> ${location}</p>
            ${excerpt ? `<p>${excerpt}</p>` : ""}
            ${body ? `<p>${body}</p>` : ""}

            <div style="margin-top:18px;display:flex;flex-wrap:wrap;gap:10px;">
              <a class="btn btn-secondary" target="_blank" href="https://wa.me/?text=${shareText}%20${shareUrl}">WhatsApp</a>
              <a class="btn btn-secondary" target="_blank" href="https://www.facebook.com/sharer/sharer.php?u=${shareUrl}">Facebook</a>
              <a class="btn btn-secondary" target="_blank" href="https://twitter.com/intent/tweet?text=${shareText}&url=${shareUrl}">X</a>
              <a class="btn btn-secondary" target="_blank" href="https://t.me/share/url?url=${shareUrl}&text=${shareText}">Telegram</a>
            </div>
          </article>
        `;
      }).join("");
    })
    .catch(function () {
      eventsContainer.innerHTML = `
        <article>
          <time>Error</time>
          <h2>Unable to load events</h2>
          <p>Please refresh the page or try again later.</p>
        </article>
      `;
    });
});

function formatEventDate(dateString) {
  const date = new Date(String(dateString).replace(" ", "T"));

  if (isNaN(date.getTime())) {
    return dateString;
  }

  return date.toLocaleString("en-GB", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit"
  });
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}