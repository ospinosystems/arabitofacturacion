<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Faker\Generator as Faker;

class CreateInventariosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $faker = new Faker;
        
        Schema::create('inventarios', function (Blueprint $table) {
            $table->increments('id');
            $table->string("codigo_barras")->unique();
            $table->string("codigo_proveedor")->nullable()->default(null);
            $table->integer("id_proveedor")->nullable()->default(null);
            $table->integer("id_categoria")->nullable()->default(null);
            $table->string("id_marca")->nullable()->default(null);
            $table->string("unidad")->nullable()->default("UND");
            $table->string("id_deposito")->nullable()->default(null);
            $table->string("descripcion");
            $table->decimal("iva",5,2)->nullable()->default(0);
            $table->decimal("porcentaje_ganancia",3,2)->nullable()->default(0);
            $table->decimal("precio_base",8,3)->nullable()->default(0);
            $table->decimal("precio",8,3)->default(0);
            $table->decimal("precio1",8,3)->nullable();
            $table->decimal("precio2",8,3)->nullable();
            $table->decimal("precio3",8,3)->nullable();
            $table->integer("bulto")->nullable();
            $table->integer("stockmin")->nullable();
            $table->integer("stockmax")->nullable();

            $table->decimal("cantidad",9,2)->default(0);

            $table->decimal("cantidad_garantia",10,2)->nullable();
            $table->decimal("cantidad_entransito",10,2)->nullable();
            $table->decimal("cantidad_porentregar",10,2)->nullable();


            $table->boolean("push")->nullable()->default(0);
            $table->integer('id_vinculacion')->nullable();

            $table->integer('activo')->nullable()->default(1);
            $table->integer('last_mov')->nullable();
            $table->unique(["id_vinculacion"]);
            $table->timestamps();
        });

        $inventario = [
        ];
        $arr = [];
    foreach ($inventario as $key => $value) {
        array_push($arr, 
            [
                // "id" => $value[0],
                "codigo_proveedor" => $value[0],
                "codigo_barras" => "MAN".$value[0],
                "id_proveedor" => 1,
                "id_categoria" => 1,
                "id_marca" => 1,
                "unidad" => "UND",
                "id_deposito" => 1,
                "descripcion" => $value[1],
                "iva" => 0,
                "porcentaje_ganancia" => 0,
                "cantidad" => $value[2],
                "precio_base" => $value[4],
                "precio" => $value[5],
                "precio3" => $value[3],

            ]
        );
    }
    //DB::table("inventarios")->insert($arr); 
        
        /* $arrinsert = [];
        
        $con = new Mysqli("localhost","root","","administrativo2");
        
        $sql = $con->query("
        SELECT articulos.*,
        (SELECT SUM(cantidad) 
            FROM inventario
            WHERE articulo = articulos.id) ct
            
            FROM articulos articulos order by ct desc");
            
        $i = 1;
        while($row = $sql->fetch_assoc()){
            array_push($arrinsert,[
                'codigo_barras' => $row['id'],
                'codigo_proveedor' => $row['codigo'],
                'id_proveedor' => 1,
                'id_categoria' => 14,
                'descripcion' => $row['descripcion'],
                'precio_base' => $row['costod'],
                'precio' => $row['preciod'],
                'cantidad' => $row['ct']?$row['ct']:0,  
            ]);
            if ($i==1000 OR $i==2000 OR $i==3583) {
                DB::table("inventarios")->insert($arrinsert);
                $arrinsert = [];
            }
            
            $i++;
                
        } */

        
        



        
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventarios');
    }


}


