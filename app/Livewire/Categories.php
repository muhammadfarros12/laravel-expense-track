<?php

namespace App\Livewire;

use App\Models\Category;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Title;

#[Title('Categories - Expense Tracker')]
class Categories extends Component
{

    public $name = '';
    public $color = '#3B82F6';
    public $icon = '';
    public $editingId = null;
    public $isEditing = false;
    public $colors = [
        '#EF4444', // Red
        '#F97316', // Orange
        '#F59E0B', // Amber
        '#EAB308', // Yellow
        '#84CC16', // Lime
        '#22C55E', // Green
        '#10B981', // Emerald
        '#14B8A6', // Teal
        '#06B6D4', // Cyan
        '#0EA5E9', // Sky
        '#3B82F6', // Blue
        '#6366F1', // Indigo
        '#8B5CF6', // Violet
        '#A855F7', // Purple
        '#D946EF', // Fuchsia
        '#EC4899', // Pink
        '#F43F5E' // Rose
    ];

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255|unique:categories,name,' . ($this->editingId ?: 'NULL') . ',id,user_id,' . auth()->id(),
            'color' => 'required|string',
            'icon' => 'nullable|string|max:255'
        ];
    }

    protected $messages = [
        'name.required' => 'Category name is required.',
        'name.unique' => 'You already have a category with this name.',
        'color.required' => 'Category color is required.'
    ];

    // computed property
    #[Computed]
    public function categories()
    {
        return Category::withCount('expenses')
            ->where('user_id', auth()->id())->orderby('name')->get();
    }

    public function save()
    {
        $this->validate();

        if ($this->isEditing && $this->editingId) {
            // update existing category
            $category = Category::findOrFail($this->editingId);
            if ($category->user_id !== auth()->id()) {
                session()->flash('error', 'You are not authorized to edit this category.');
                abort(403);
                return;
            }

            $category->update([
                'name' => $this->name,
                'color' => $this->color,
                'icon' => $this->icon,
            ]);

            session()->flash('message', 'Category updated successfully.');
        } else {
            // create new category
            Category::create([
                'user_id' => auth()->id(),
                'name' => $this->name,
                'color' => $this->color,
                'icon' => $this->icon,
            ]);

            session()->flash('message', 'Category created successfully.');
        }


        $this->reset(['name', 'color', 'icon', 'editingId', 'isEditing']);
    }

    function edit($id)
    {
        $category = Category::where('user_id', auth()->id())->findOrFail($id);
        $this->name = $category->name;
        $this->color = $category->color;
        $this->icon = $category->icon;
        $this->editingId = $category->id;
        $this->isEditing = true;
    }

    public function cancelEdit()
    {
        $this->reset(['name', 'color', 'icon', 'editingId', 'isEditing']);
        $this->color = '#3B82F6';
    }

    public function delete($category_id){
        $category = Category::findOrFail($category_id);
        if ($category->user_id !== auth()->id()) {
            session()->flash('error', 'You are not authorized to delete this category.');
            abort(403);
            return;
        }

        if ($category->expenses()->count() > 0) {
            session()->flash('error', 'Cannot delete category with associated expenses.');
            return;
        }

        $category->delete();
        session()->flash('message', 'Category deleted successfully.');
    }


    public function render()
    {
        return view('livewire.categories', [
            'categories' => $this->categories(),
        ]);
    }
}
