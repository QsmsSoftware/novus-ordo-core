class ActionLinkHelper {
    static renderActionLink(title, onclick, selected = false) {
        return `<a class="${selected ? "selected-action-link" : "action-link"}" href="javascript:void(0)" onclick="${onclick}">${title}</a>`;
    }
    static renderTerritoryLink(territory) {
        return ActionLinkHelper.renderActionLink(`${territory.name} (${territory.owner_nation_id ? nationsById.get(territory.owner_nation_id).usual_name : "neutral"})`, `selectTerritory(${territory.territory_id})`);
    }
}

class NewsDisplayService {
    #news;
    #processedNews = null;
    #entitiesByTypeThenId = new Map();
    constructor(news, nationsById, leadersById, territoriesById) {
        this.#entitiesByTypeThenId.set("nation", nationsById);
        this.#entitiesByTypeThenId.set("leader", leadersById);
        this.#entitiesByTypeThenId.set("territory", territoriesById)
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

            if (entityType == "territory" && entityAttribute == "name") {
                return ActionLinkHelper.renderTerritoryLink(entity);
            }

            return entity[entityAttribute] === undefined ? match : entity[entityAttribute];
        });
    }
    updateNews(targetComponent) {
        this.#processedNews ??= this.#news.map(n => this.#renderNewsContent(n.content));

        targetComponent.innerHTML = '<ul>'
            + this.#processedNews.map(n => `<li>${n}</li>`).join("")
            + '</ul>';
    }
}