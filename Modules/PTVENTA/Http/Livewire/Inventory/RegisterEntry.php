<?php

namespace Modules\PTVENTA\Http\Livewire\Inventory;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\SICA\Entities\Element;
use Modules\SICA\Entities\Inventory;
use Modules\SICA\Entities\Movement;
use Modules\SICA\Entities\MovementDetail;
use Modules\SICA\Entities\MovementResponsibility;
use Modules\SICA\Entities\MovementType;
use Modules\SICA\Entities\Person;
use Modules\SICA\Entities\ProductiveUnit;
use Modules\SICA\Entities\ProductiveUnitWarehouse;
use Modules\SICA\Entities\WarehouseMovement;
use Illuminate\Support\Facades\Gate;
use Modules\PTVENTA\Http\Controllers\PUW;

class RegisterEntry extends Component
{

    public $puw;
    public $products;
    public $destinations;
    public $productive_units;
    public $dpu_id;
    public $puwarehouses;
    public $dpuw_id;
    public $delivery_person;

    // Datos del producto
    public $product_element_id;
    public $product_price;
    public $product_amount;
    public $product_production_date;
    public $product_expiration_date;
    public $product_lot_number;
    public $product_inventory_code;
    public $product_mark;
    public $product_destination = 'Producción';

    public Collection $selected_products;
    public $observation;

    public function __construct(){
        $this->selected_products = collect();
    }

    public function mount(){
        $this->defaultAction();
    }

    public function render(){
        return view('ptventa::livewire.inventory.register-entry');
    }

    // Reset principal
    public function defaultAction(){
        $this->reset();
        $this->puw = PUW::getAppPuw();
        $this->products = Element::whereNotNull('price')->orderBy('name','ASC')->get();
        $this->productive_units = ProductiveUnit::whereHas('productive_unit_warehouses')->orderBy('name','ASC')->get();
        $this->destinations = getEnumValues('inventories','destination');
    }

    // Cambio unidad productiva de origen
    public function updatedDpuId($value){
        $this->reset('puwarehouses','dpuw_id','delivery_person');
        if(!empty($value)){
            $this->puwarehouses = ProductiveUnitWarehouse::where('productive_unit_id',$this->dpu_id)->get();
        }
    }

    // Cambio bodega de origen
    public function updatedDpuwId($value){
        $this->reset('delivery_person');
        if(!empty($value)){
            $dp_id = ProductiveUnitWarehouse::findOrFail($value)->productive_unit->person_id;
            $this->delivery_person = Person::findOrFail($dp_id);
        }
    }

    // Detectar selección de producto
    public function updatedProductElementId($value){
        $this->loadExistingInventory();
    }

    // Detectar cambio de lote
    public function updatedProductLotNumber($value){
        $this->loadExistingInventory();
    }

    // Cargar datos si ya existe en inventario
    protected function loadExistingInventory(){
        if (!empty($this->product_element_id) && !empty($this->product_lot_number)) {
            $existing = Inventory::where('productive_unit_warehouse_id', $this->puw->id)
                ->where('element_id', $this->product_element_id)
                ->where('lot_number', $this->product_lot_number)
                ->first();

            if ($existing) {
                $this->product_price = $existing->price;
                $this->product_amount = $existing->amount;
                $this->product_production_date = $existing->production_date;
                $this->product_expiration_date = $existing->expiration_date;
                $this->product_inventory_code = $existing->inventory_code;
                $this->product_mark = $existing->mark;
                $this->product_destination = $existing->destination;

                $this->emit('message','info','Producto existente',
                    'Se cargaron automáticamente los datos previos de este producto en inventario. Puedes modificarlos si es necesario.');
            }
        }
    }

    // Agregar producto a lista
    public function addProduct(){
        if($this->product_amount == 0){
            $this->emit('message','alert-warning',null,'El producto que intentas agregar debe tener una cantidad superior a 0.');
            return;
        }

        $product = Element::find($this->product_element_id);

        $exists = $this->selected_products->contains(function ($p) {
            return $p['product_element_id'] == $this->product_element_id
                && $p['product_lot_number'] == $this->product_lot_number;
        });

        if ($exists) {
            $this->emit('message','alert-warning','Producto duplicado',
                'Este producto ya fue agregado. Verifica los datos: Destination, N° Lote y Producción.');
            return;
        }

        $this->selected_products->push([
            'product_element_id' => $this->product_element_id,
            'product_name' => $product->product_name,
            'product_price' => $this->product_price ?? $product->price,
            'product_amount' => $this->product_amount,
            'product_production_date' => $this->product_production_date,
            'product_expiration_date' => $this->product_expiration_date,
            'product_lot_number' => $this->product_lot_number,
            'product_inventory_code' => $this->product_inventory_code,
            'product_mark' => $this->product_mark,
            'product_destination' => $this->product_destination
        ]);

        $this->resetValuesProduct();
    }

    // Reset campos del producto
    public function resetValuesProduct(){
        $this->reset('product_element_id','product_price','product_amount',
            'product_production_date','product_expiration_date','product_lot_number',
            'product_inventory_code','product_mark','product_destination');
        $this->product_destination = 'Producción';
    }

    // Editar producto seleccionado
    public function editProduct($product_index){
        $product = $this->selected_products[$product_index];
        $this->product_element_id = $product['product_element_id'];
        $this->product_price = $product['product_price'];
        $this->product_amount = $product['product_amount'];
        $this->product_production_date = $product['product_production_date'];
        $this->product_expiration_date = $product['product_expiration_date'];
        $this->product_lot_number = $product['product_lot_number'];
        $this->product_inventory_code = $product['product_inventory_code'];
        $this->product_mark = $product['product_mark'];
        $this->product_destination = $product['product_destination'];
        $this->selected_products->forget($product_index);
    }

    // Eliminar producto
    public function deleteProduct($product_index){
        $this->selected_products->forget($product_index);
    }

    // Registrar entrada de inventario
    public function registerEntry(){
        Gate::authorize('haveaccess','ptventa.admin-cashier.inventory.store');

        if($this->selected_products->isEmpty()){
            $this->emit('message','alert-warning',null,'Es necesario agregar al menos un producto.');
            return;
        }
        if(empty($this->dpu_id)){
            $this->emit('message','alert-warning',null,'Es necesario seleccionar una unidad productiva de origen.');
            return;
        }
        if(empty($this->dpuw_id)){
            $this->emit('message','alert-warning',null,'Es necesario seleccionar una bodega de origen.');
            return;
        }

        try{
            DB::beginTransaction();

            $current_datetime = now()->milliseconds(0);

            $movementType = MovementType::where('name','Movimiento Interno')->firstOrFail();

            $movement = Movement::create([
                'registration_date' => $current_datetime,
                'movement_type_id' => $movementType->id,
                'voucher_number' => 0,
                'state' => 'Aprobado',
                'observation' => $this->observation,
                'price' => 0
            ]);

            $movement_price = 0;
            foreach ($this->selected_products as $product) {
                // Buscar inventario existente
                $inventory = Inventory::where('productive_unit_warehouse_id',$this->puw->id)
                    ->where('element_id',$product['product_element_id'])
                    ->where('lot_number',$product['product_lot_number'])
                    ->first();

                if ($inventory) {
                    // Actualizar cantidad
                    $inventory->update([
                        'amount' => $inventory->amount + $product['product_amount'],
                    ]);
                } else {
                    // Crear nuevo
                    $inventory = Inventory::create([
                        'person_id'=>$this->puw->productive_unit->person_id,
                        'productive_unit_warehouse_id'=>$this->puw->id,
                        'element_id'=>$product['product_element_id'],
                        'destination'=>$product['product_destination'],
                        'price'=>$product['product_price'],
                        'amount'=>$product['product_amount'],
                        'stock'=>0,
                        'production_date'=>$product['product_production_date'],
                        'lot_number'=>$product['product_lot_number'],
                        'expiration_date'=>$product['product_expiration_date'],
                        'state'=>'Disponible',
                        'mark'=>$product['product_mark'],
                        'inventory_code'=>$product['product_inventory_code']
                    ]);
                }

                MovementDetail::create([
                    'movement_id' => $movement->id,
                    'inventory_id' => $inventory->id,
                    'amount' => $product['product_amount'],
                    'price' => $product['product_price']
                ]);

                $movement_price += $product['product_amount'] * $product['product_price'];
            }

            // Responsables
            MovementResponsibility::create([
                'person_id' => $this->delivery_person->id,
                'movement_id' => $movement->id,
                'role' => 'ENTREGA',
                'date' => $current_datetime
            ]);
            MovementResponsibility::create([
                'person_id' => Auth::user()->person_id,
                'movement_id' => $movement->id,
                'role' => 'RECIBE',
                'date' => $current_datetime
            ]);

            // Movimientos de bodega
            WarehouseMovement::create([
                'productive_unit_warehouse_id' => $this->dpuw_id,
                'movement_id' => $movement->id,
                'role' => 'Entrega'
            ]);
            WarehouseMovement::create([
                'productive_unit_warehouse_id' => $this->puw->id,
                'movement_id' => $movement->id,
                'role' => 'Recibe'
            ]);

            // Comprobante
            $movementType->update(['consecutive' => $movementType->consecutive + 1]);
            $movement->update([
                'voucher_number' => $movementType->consecutive,
                'price' => $movement_price,
            ]);

            DB::commit();

            $this->emit('message','success','Operación realizada','Entrada de inventario registrada exitosamente.');

            $final_movement = Movement::with(
                'warehouse_movements.productive_unit_warehouse.warehouse',
                'movement_details.inventory.element.measurement_unit',
                'movement_responsibilities.person'
            )->find($movement->id);

            $this->emit('printTicket',$final_movement);

            $this->defaultAction();

        }catch(Exception $e){
            DB::rollBack();
            $this->emit('message','error','Operación rechazada',$e->getMessage());
        }
    }
}
