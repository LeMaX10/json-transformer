<?php
namespace lemax10\JsonTransformer;

interface JsonTransformer {
    /**
     * Метод описывает отдаваемый тип модели после трансформации
     * @return string
     */
    public function getAlias() : string;

    /**
     * Метод описывает "алиасы" атрибутов модели
     * @return array
     */
    public function getAliasedProperties() : array;

    /**
     * Метод описывает скрытые атрибуты модели
     * @return array
     */
    public function getHideProperties() : array;

    /**
     * Метод описывает первичный ключ модели
     * @return array
     */
    public function getIdProperties() : array;

    /**
     * Метод описывает ссылки модели
     * @return array
     */
    public function getUrls() : array;

    /**
     * Метод описывает зависимости модели которые могут быть загружены
     * @return array
     */
    public function getRelationships() : array;

    /**
     * Метод описывает мета данные ответа после трансформации
     * @return array
     */
    public function getMeta() : array;
}