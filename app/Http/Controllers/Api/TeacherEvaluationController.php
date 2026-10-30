<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherEvaluationController extends Controller
{
    private function ownGroup(Request $request, int $groupId): bool
    {
        return DB::table('grupo')->where('id',$groupId)
            ->where('docente_id',$request->user()->getAuthIdentifier())->exists();
    }

    public function index(Request $request, int $groupId): JsonResponse
    {
        if (!$this->ownGroup($request,$groupId)) return response()->json(['message'=>'Grupo no disponible.'],404);
        $rows = DB::table('evaluacion')->where('grupo_id',$groupId)->orderBy('numero')->get();
        foreach ($rows as $row) {
            $row->rubros = DB::table('rubro_evaluacion')->where('evaluacion_id',$row->id)
                ->orderBy('id')->get(['id','tipo','porcentaje']);
        }
        return response()->json(['data'=>$rows]);
    }

    public function store(Request $request, int $groupId): JsonResponse
    {
        $data=$request->validate([
            'nombre'=>['required','string','max:100'], 'numero'=>['required','integer','min:1'],
            'rubros'=>['required','array','min:1','max:30'],
            'rubros.*.tipo'=>['required','string','max:50'],
            'rubros.*.porcentaje'=>['required','numeric','gt:0','lte:100','decimal:0,2'],
        ]);
        if (!$this->ownGroup($request,$groupId)) return response()->json(['message'=>'Grupo no disponible.'],404);
        $total = array_sum(array_map(fn($r)=>(int)round((float)$r['porcentaje']*100),$data['rubros']));
        if ($total!==10000) return response()->json(['message'=>'Los rubros deben sumar 100 %.'],422);
        try {
            $id=DB::transaction(function () use($groupId,$data) {
                $id=DB::table('evaluacion')->insertGetId(['grupo_id'=>$groupId,
                    'nombre'=>$data['nombre'],'numero'=>$data['numero']]);
                foreach ($data['rubros'] as $r) {
                    DB::table('rubro_evaluacion')->insert(['evaluacion_id'=>$id,
                        'tipo'=>$r['tipo'],'porcentaje'=>$r['porcentaje']]);
                }
                // El trigger diferido comprueba el 100% al confirmar.
                return $id;
            });
        } catch (QueryException $e) { return response()->json(['message'=>'No se pudo crear la evaluacion.'],422); }
        return response()->json(['data'=>['id'=>$id]],201);
    }

    public function gradeRubric(Request $request, int $rubroId): JsonResponse
    {
        $data=$request->validate(['alumno_id'=>['required','integer','min:1'],
            'valor'=>['required','numeric','between:0,100']]);
        $rubro=DB::table('rubro_evaluacion as r')->join('evaluacion as e','e.id','=','r.evaluacion_id')
            ->join('grupo as g','g.id','=','e.grupo_id')
            ->where('r.id',$rubroId)->where('g.docente_id',$request->user()->getAuthIdentifier())
            ->first(['r.id','g.id as grupo_id']);
        if (!$rubro || !DB::table('alumno_grupo')->where('grupo_id',$rubro->grupo_id)
                ->where('alumno_id',$data['alumno_id'])->exists()) {
            return response()->json(['message'=>'Rubro o alumno no disponible.'],404);
        }
        DB::table('calificacion_rubro')->updateOrInsert(['rubro_id'=>$rubroId,
            'alumno_id'=>$data['alumno_id']], ['valor'=>$data['valor']]);
        return response()->json(['data'=>['rubro_id'=>$rubroId,'alumno_id'=>$data['alumno_id'],
            'valor'=>$data['valor']]]);
    }
}
