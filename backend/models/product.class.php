<?php
class Product {
    public $id;
    public $category_id;
    public $sku;
    public $name;
    public $slug;
    public $description;
    public $price;
    public $currency;
    public $stock_quantity;
    public $image_path;

    public function __construct($data) {
        $this->id = $data['id'] ?? null;
        $this->category_id = $data['category_id'] ?? null;
        $this->sku = $data['sku'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->slug = $data['slug'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->price = $data['price'] ?? null;
        $this->currency = $data['currency'] ?? 'EUR';
        $this->stock_quantity = $data['stock_quantity'] ?? 0;
        $this->image_path = $data['image_path'] ?? null;
    }
}