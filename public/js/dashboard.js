class NewsDisplayService {
    #news;
    #processedNews = null;
    #entitiesByTypeThenId = new Map();
    constructor(news, nationsById, leadersById) {
        this.#entitiesByTypeThenId.set("nation", nationsById);
        this.#entitiesByTypeThenId.set("leader", leadersById);
        this.#news = news;
    }
    #renderNewsContent(rawContent) {
        return rawContent.replaceAll(/##(\w+)#(\d+)#(\w+)##/g, (match, entityType, entityIdAsString, entityAttribute) => {
            if (!this.#entitiesByTypeThenId.has(entityType)) {
                return match;
            }
            let entitiesById = this.#entitiesByTypeThenId.get(entityType);

            let entityId = parseInt(entityIdAsString);
            if (!entitiesById.has(entityId)) {
                return match;
            }
            let entity = entitiesById.get(entityId);

            return entity[entityAttribute] === undefined ? match : entity[entityAttribute];
        });
    }
    updateNews(targetComponent) {
        this.#processedNews ??= this.#news.map(n => this.#renderNewsContent(n.content));

        targetComponent.innerHTML = '<ul>'
            + this.#processedNews.map(n => `<li>${n}</li>`)
            + '</ul>';
    }
}