const MEDIA_API_BASE = location.pathname.startsWith('/SME/') ? '/SME' : '';

const MEDIA_POSTS = new Map();
const MEDIA_PAGE_TITLE = document.title;

let mediaLastFocusedElement = null;

function mediaEscape(value) {
  return String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }[character]));
}

function mediaTypeLabel(type) {
  return String(type || 'news')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, character => character.toUpperCase());
}

function mediaPostKey(post, index = 0) {
  const key = post.slug || post.id || `article-${index + 1}`;
  return String(key).trim();
}

function mediaArticleHash(key) {
  return `#article=${encodeURIComponent(String(key))}`;
}

function mediaArticleUrl(key) {
  return (
    location.origin +
    location.pathname +
    location.search +
    mediaArticleHash(key)
  );
}

function mediaKeyFromHash() {
  let hash = location.hash.replace(/^#/, '');

  if (!hash) {
    return '';
  }

  /*
   * Support both:
   * #article=article-slug
   * #article-slug
   */
  if (hash.startsWith('article=')) {
    hash = hash.slice('article='.length);
  }

  try {
    return decodeURIComponent(hash);
  } catch (error) {
    return hash;
  }
}

function mediaPlainText(value) {
  return String(value || '')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/p>/gi, '\n\n')
    .replace(/<\/div>/gi, '\n\n')
    .replace(/<\/li>/gi, '\n')
    .replace(/<[^>]*>/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

function mediaPreview(value, maximumLength = 230) {
  const text = mediaPlainText(value);

  if (text.length <= maximumLength) {
    return text;
  }

  return text.slice(0, maximumLength).trimEnd() + '...';
}

function mediaBodyHtml(value) {
  let text = String(value || '')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/p>/gi, '\n\n')
    .replace(/<\/div>/gi, '\n\n')
    .replace(/<\/li>/gi, '\n')
    .replace(/<[^>]*>/g, '')
    .replace(/\r\n/g, '\n')
    .trim();

  if (!text) {
    return '<p>No additional article content is available.</p>';
  }

  return text
    .split(/\n{2,}/)
    .filter(paragraph => paragraph.trim() !== '')
    .map(paragraph => {
      const escapedParagraph = mediaEscape(paragraph.trim())
        .replace(/\n/g, '<br>');

      return `<p>${escapedParagraph}</p>`;
    })
    .join('');
}

function shareLinks(title, url) {
  const text = encodeURIComponent(title || document.title);
  const link = encodeURIComponent(url || location.href);
  const copyUrl = mediaEscape(url || location.href);

  return `
    <div class="share-row">
      <a
        target="_blank"
        rel="noopener noreferrer"
        href="https://wa.me/?text=${text}%20${link}"
      >
        WhatsApp
      </a>

      <a
        target="_blank"
        rel="noopener noreferrer"
        href="https://www.facebook.com/sharer/sharer.php?u=${link}"
      >
        Facebook
      </a>

      <a
        target="_blank"
        rel="noopener noreferrer"
        href="https://twitter.com/intent/tweet?text=${text}&url=${link}"
      >
        X
      </a>

      <button
        type="button"
        class="share-copy"
        data-copy="${copyUrl}"
      >
        Instagram
      </button>

      <a
        target="_blank"
        rel="noopener noreferrer"
        href="https://t.me/share/url?url=${link}&text=${text}"
      >
        Telegram
      </a>
    </div>
  `;
}

function installMediaStyles() {
  if (document.getElementById('dynamicMediaReaderStyles')) {
    return;
  }

  const style = document.createElement('style');
  style.id = 'dynamicMediaReaderStyles';

  style.textContent = `
    .media-clickable-card {
      cursor: pointer;
      transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        border-color 0.2s ease;
    }

    .media-clickable-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 16px 36px rgba(7, 35, 79, 0.13);
    }

    .post-title-link {
      display: inline;
      padding: 0;
      margin: 0;
      color: inherit;
      background: transparent;
      border: 0;
      font: inherit;
      font-weight: inherit;
      line-height: inherit;
      text-align: left;
      cursor: pointer;
    }

    .post-title-link:hover,
    .post-title-link:focus-visible {
      color: #073b8e;
      text-decoration: underline;
      text-underline-offset: 4px;
    }

    .post-summary {
      line-height: 1.75;
    }

    .read-article-button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin: 14px 0 5px;
      text-decoration: none;
    }

    .article-reader {
      position: fixed;
      inset: 0;
      z-index: 100000;
      display: grid;
      place-items: center;
      padding: 20px;
    }

    .article-reader[hidden] {
      display: none;
    }

    .article-reader-backdrop {
      position: absolute;
      inset: 0;
      background: rgba(1, 14, 35, 0.78);
      backdrop-filter: blur(4px);
    }

    .article-reader-panel {
      position: relative;
      z-index: 1;
      width: min(900px, 100%);
      max-height: 92vh;
      overflow-y: auto;
      padding: 38px;
      background: #ffffff;
      border-radius: 18px;
      box-shadow: 0 28px 80px rgba(0, 0, 0, 0.35);
    }

    .article-reader-close {
      position: sticky;
      top: 0;
      float: right;
      z-index: 2;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 42px;
      height: 42px;
      margin: -15px -15px 5px 15px;
      color: #ffffff;
      background: #073b8e;
      border: 0;
      border-radius: 50%;
      font-size: 25px;
      line-height: 1;
      cursor: pointer;
    }

    .article-reader-close:hover {
      background: #052c6a;
    }

    .article-reader-image {
      display: block;
      width: 100%;
      max-height: 460px;
      margin-bottom: 25px;
      object-fit: cover;
      border-radius: 13px;
    }

    .article-reader-type {
      display: inline-block;
      margin-bottom: 10px;
      color: #073b8e;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: 1px;
      text-transform: uppercase;
    }

    .article-reader-title {
      margin: 0 0 18px;
      color: #061d40;
      font-size: clamp(28px, 4vw, 44px);
      line-height: 1.15;
    }

    .article-reader-excerpt {
      margin: 0 0 24px;
      padding-left: 16px;
      color: #465b75;
      border-left: 4px solid #e31b23;
      font-size: 17px;
      font-weight: 600;
      line-height: 1.7;
    }

    .article-reader-body {
      color: #18283d;
      font-size: 16px;
      line-height: 1.9;
    }

    .article-reader-body p {
      margin: 0 0 20px;
    }

    .article-reader-share {
      margin-top: 30px;
      padding-top: 22px;
      border-top: 1px solid #d9e2ef;
    }

    .article-reader-share::before {
      content: "Share this article";
      display: block;
      margin-bottom: 12px;
      color: #061d40;
      font-weight: 800;
    }

    body.article-reader-open {
      overflow: hidden;
    }

    @media (max-width: 700px) {
      .article-reader {
        padding: 10px;
      }

      .article-reader-panel {
        max-height: 95vh;
        padding: 24px 20px;
        border-radius: 14px;
      }

      .article-reader-close {
        margin-right: -7px;
      }

      .article-reader-title {
        font-size: 27px;
      }

      .article-reader-excerpt {
        font-size: 15px;
      }

      .article-reader-body {
        font-size: 15px;
      }
    }
  `;

  document.head.appendChild(style);
}

function ensureArticleReader() {
  let reader = document.getElementById('articleReader');

  if (reader) {
    return reader;
  }

  document.body.insertAdjacentHTML(
    'beforeend',
    `
      <div
        class="article-reader"
        id="articleReader"
        role="dialog"
        aria-modal="true"
        aria-labelledby="articleReaderTitle"
        hidden
      >
        <div
          class="article-reader-backdrop"
          data-close-article
        ></div>

        <article class="article-reader-panel">
          <button
            type="button"
            class="article-reader-close"
            data-close-article
            aria-label="Close article"
          >
            ×
          </button>

          <img
            class="article-reader-image"
            data-article-image
            alt=""
            hidden
          >

          <span
            class="article-reader-type"
            data-article-type
          ></span>

          <h1
            class="article-reader-title"
            id="articleReaderTitle"
            data-article-title
          ></h1>

          <p
            class="article-reader-excerpt"
            data-article-excerpt
            hidden
          ></p>

          <div
            class="article-reader-body"
            data-article-body
          ></div>

          <div
            class="article-reader-share"
            data-article-share
          ></div>
        </article>
      </div>
    `
  );

  return document.getElementById('articleReader');
}

function openMediaArticle(key, updateUrl = true) {
  const normalizedKey = String(key || '');
  const post = MEDIA_POSTS.get(normalizedKey);

  if (!post) {
    return;
  }

  const reader = ensureArticleReader();

  const titleElement = reader.querySelector('[data-article-title]');
  const typeElement = reader.querySelector('[data-article-type]');
  const excerptElement = reader.querySelector('[data-article-excerpt]');
  const bodyElement = reader.querySelector('[data-article-body]');
  const imageElement = reader.querySelector('[data-article-image]');
  const shareElement = reader.querySelector('[data-article-share]');
  const closeButton = reader.querySelector('.article-reader-close');

  const title = String(post.title || 'Campaign update');
  const excerpt = String(post.excerpt || '').trim();
  const articleContent = post.body || post.excerpt || '';
  const articleUrl = mediaArticleUrl(normalizedKey);

  titleElement.textContent = title;
  typeElement.textContent = mediaTypeLabel(post.type);

  if (excerpt) {
    excerptElement.textContent = excerpt;
    excerptElement.hidden = false;
  } else {
    excerptElement.textContent = '';
    excerptElement.hidden = true;
  }

  bodyElement.innerHTML = mediaBodyHtml(articleContent);
  shareElement.innerHTML = shareLinks(title, articleUrl);

  if (post.featured_image) {
    imageElement.src = String(post.featured_image);
    imageElement.alt = title;
    imageElement.hidden = false;
  } else {
    imageElement.removeAttribute('src');
    imageElement.alt = '';
    imageElement.hidden = true;
  }

  mediaLastFocusedElement = document.activeElement;

  reader.hidden = false;
  document.body.classList.add('article-reader-open');
  document.title = `${title} | ${MEDIA_PAGE_TITLE}`;

  if (updateUrl) {
    history.pushState(
      {
        mediaArticle: normalizedKey
      },
      '',
      location.pathname +
        location.search +
        mediaArticleHash(normalizedKey)
    );
  }

  setTimeout(() => {
    closeButton?.focus();
  }, 50);
}

function closeMediaArticle(updateUrl = true) {
  const reader = document.getElementById('articleReader');

  if (!reader || reader.hidden) {
    return;
  }

  reader.hidden = true;
  document.body.classList.remove('article-reader-open');
  document.title = MEDIA_PAGE_TITLE;

  if (
    updateUrl &&
    location.hash.replace(/^#/, '').startsWith('article=')
  ) {
    history.replaceState(
      null,
      '',
      location.pathname + location.search
    );
  }

  if (
    mediaLastFocusedElement &&
    typeof mediaLastFocusedElement.focus === 'function'
  ) {
    mediaLastFocusedElement.focus();
  }
}

function openArticleFromCurrentUrl() {
  const key = mediaKeyFromHash();

  if (!key) {
    closeMediaArticle(false);
    return;
  }

  if (MEDIA_POSTS.has(key)) {
    openMediaArticle(key, false);
  }
}

function renderMediaPosts(posts) {
  const grid = document.getElementById('postGrid');

  if (!grid || !posts.length) {
    return;
  }

  MEDIA_POSTS.clear();

  grid.innerHTML = posts
    .map((post, index) => {
      const key = mediaPostKey(post, index);

      MEDIA_POSTS.set(key, post);

      const title = String(post.title || 'Campaign update');
      const articleUrl = mediaArticleUrl(key);

      const image = post.featured_image
        ? `
          <img
            class="post-image"
            src="${mediaEscape(post.featured_image)}"
            alt="${mediaEscape(title)}"
            loading="lazy"
          >
        `
        : '';

      const summary =
        String(post.excerpt || '').trim() ||
        mediaPreview(post.body || '', 230);

      return `
        <article
          class="post-card media-clickable-card"
          data-post-key="${mediaEscape(key)}"
          data-title="${mediaEscape(title)}"
        >
          ${image}

          <span>
            ${mediaEscape(mediaTypeLabel(post.type))}
          </span>

          <h2>
            <button
              type="button"
              class="post-title-link"
              data-open-article="${mediaEscape(key)}"
            >
              ${mediaEscape(title)}
            </button>
          </h2>

          ${
            summary
              ? `
                <p class="post-summary">
                  ${mediaEscape(summary)}
                </p>
              `
              : ''
          }

          <button
            type="button"
            class="btn read-article-button"
            data-open-article="${mediaEscape(key)}"
          >
            Read Full Article
          </button>

          ${shareLinks(title, articleUrl)}
        </article>
      `;
    })
    .join('');

  openArticleFromCurrentUrl();
}

const NEWS_PAGE_ALLOWED_TYPES = [
  'news',
  'article',
  'press_statement',
  'speech'
];

async function loadMediaPosts() {
  try {
    const response = await fetch(
      `${MEDIA_API_BASE}/api/content.php`,
      {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json'
        }
      }
    );

    const responseText = await response.text();

    let json;

    try {
      json = JSON.parse(responseText);
    } catch (error) {
      throw new Error(
        'The news backend did not return valid JSON.'
      );
    }

    if (!response.ok || !json.success) {
      throw new Error(
        json.message || 'The news articles could not be loaded.'
      );
    }

    if (!Array.isArray(json.posts)) {
      throw new Error(
        'The news backend returned an invalid article list.'
      );
    }

    const newsPosts = json.posts.filter(post => {
      const type = String(post.type || '').toLowerCase();

      return NEWS_PAGE_ALLOWED_TYPES.includes(type);
    });

    renderMediaPosts(newsPosts);
  } catch (error) {
    console.warn(
      'Could not load dynamic media posts.',
      error
    );
  }
}

function wireMediaSearch() {
  const input = document.getElementById('mediaSearch');

  if (!input) {
    return;
  }

  input.addEventListener('input', () => {
    const query = input.value.trim().toLowerCase();

    document.querySelectorAll('.post-card').forEach(card => {
      card.hidden =
        query !== '' &&
        !card.textContent.toLowerCase().includes(query);
    });
  });
}

function wireCopyButtons() {
  document.addEventListener('click', async event => {
    const button = event.target.closest('.share-copy');

    if (!button) {
      return;
    }

    event.stopPropagation();

    const copyValue =
      button.dataset.copy || location.href;

    try {
      await navigator.clipboard.writeText(copyValue);

      const previousText = button.textContent;

      button.textContent = 'Link copied';

      setTimeout(() => {
        button.textContent = previousText;
      }, 1500);
    } catch (error) {
      alert('Copy this link: ' + copyValue);
    }
  });
}

function wireArticleReader() {
  document.addEventListener('click', event => {
    const openButton = event.target.closest(
      '[data-open-article]'
    );

    if (openButton) {
      event.preventDefault();
      event.stopPropagation();

      openMediaArticle(
        openButton.dataset.openArticle
      );

      return;
    }

    const closeButton = event.target.closest(
      '[data-close-article]'
    );

    if (closeButton) {
      event.preventDefault();
      closeMediaArticle();
      return;
    }

    const card = event.target.closest(
      '.post-card[data-post-key]'
    );

    if (!card) {
      return;
    }

    /*
     * Do not open the article when a visitor clicks
     * a social link, button or form control.
     */
    if (
      event.target.closest(
        'a, button, input, textarea, select, label'
      )
    ) {
      return;
    }

    openMediaArticle(card.dataset.postKey);
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      closeMediaArticle();
    }
  });

  window.addEventListener(
    'popstate',
    openArticleFromCurrentUrl
  );

  window.addEventListener(
    'hashchange',
    openArticleFromCurrentUrl
  );
}

document.addEventListener('DOMContentLoaded', () => {
  installMediaStyles();
  ensureArticleReader();
  wireMediaSearch();
  wireCopyButtons();
  wireArticleReader();
  loadMediaPosts();
});