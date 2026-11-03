<?php

namespace FastRaven\Types;

/**
 * Enum ProjectFolderType
 *
 * ProjectFolderType is an enum that defines the different folders required to run a FastRaven project.
 */
enum ProjectFolderType: string {
    case CONFIG = "config" . DIRECTORY_SEPARATOR;
    case CONFIG_ENV = self::CONFIG->value . "env" . DIRECTORY_SEPARATOR;
    case CONFIG_ROUTER = self::CONFIG->value . "router" . DIRECTORY_SEPARATOR;
    case PUBLIC = "public" . DIRECTORY_SEPARATOR;
    case PUBLIC_ASSETS = self::PUBLIC->value . "assets" . DIRECTORY_SEPARATOR;
    case PUBLIC_ASSETS_CSS = self::PUBLIC_ASSETS->value . "css" . DIRECTORY_SEPARATOR;
    case PUBLIC_ASSETS_JS = self::PUBLIC_ASSETS->value . "js" . DIRECTORY_SEPARATOR;
    case PUBLIC_ASSETS_IMG = self::PUBLIC_ASSETS->value . "img" . DIRECTORY_SEPARATOR;
    case PUBLIC_ASSETS_FONTS = self::PUBLIC_ASSETS->value . "fonts" . DIRECTORY_SEPARATOR;
    case SRC = "src" . DIRECTORY_SEPARATOR;
    case SRC_VIEWS = self::SRC->value . "views" . DIRECTORY_SEPARATOR;
    case SRC_API = self::SRC->value . "api" . DIRECTORY_SEPARATOR;
    case SRC_CDN = self::SRC->value . "cdn" . DIRECTORY_SEPARATOR;
    case SRC_LIB = self::SRC->value . "lib" . DIRECTORY_SEPARATOR;
    case SRC_WEB = self::SRC->value . "web" . DIRECTORY_SEPARATOR;
    case SRC_WEB_TEMPLATES = self::SRC_WEB->value . "templates" . DIRECTORY_SEPARATOR;
    case SRC_WEB_TEMPLATES_FRAGMENTS = self::SRC_WEB_TEMPLATES->value . "fragments" . DIRECTORY_SEPARATOR;
    case SRC_WEB_TEMPLATES_MAILS = self::SRC_WEB_TEMPLATES->value . "mails" . DIRECTORY_SEPARATOR;
    case SRC_WEB_TEMPLATES_PAGES = self::SRC_WEB_TEMPLATES->value . "pages" . DIRECTORY_SEPARATOR;
    case SRC_WEB_ASSETS = self::SRC_WEB->value . "assets" . DIRECTORY_SEPARATOR;
    case SRC_WEB_ASSETS_SCSS = self::SRC_WEB_ASSETS->value . "scss" . DIRECTORY_SEPARATOR;
    case SRC_WEB_ASSETS_JS = self::SRC_WEB_ASSETS->value . "js" . DIRECTORY_SEPARATOR;
    case SRC_WEB_ASSETS_LANG = self::SRC_WEB_ASSETS->value . "lang" . DIRECTORY_SEPARATOR;
    case STORAGE = "storage" . DIRECTORY_SEPARATOR;
    case STORAGE_CACHE = self::STORAGE->value . "cache" . DIRECTORY_SEPARATOR;
    case STORAGE_LOGS = self::STORAGE->value . "logs" . DIRECTORY_SEPARATOR;
    case STORAGE_UPLOADS = self::STORAGE->value . "uploads" . DIRECTORY_SEPARATOR;
}