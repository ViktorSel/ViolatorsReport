<?php

namespace Ast\ViolatorsReport\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ast\ViolatorsReport\Services\Export;
use Ast\ViolatorsReport\Services\Response;
use Ast\ViolatorsReport\Services\ViolatorsReportExport;

ini_set('memory_limit', -1);
set_time_limit(10800);

class ViolatorsReportController extends Controller
{
    protected $date;
    protected $section;

    public const ATTR_DATE = 'date';
    public const ATTR_FORMAT = 'formatDocument';
    public const ATTR_SECTION = 'section';
    public const ARRAY_ATTR = [self::ATTR_DATE, self::ATTR_SECTION,self::ATTR_FORMAT];

     public function __construct()
     {
     }

     public function get_sections(): \Illuminate\Http\JsonResponse
     {
         $data = DB::select(
         /** @lang PostgresSQL */'select "society","sectionDescription" as "desc" from lvl2pdb."sections" where "enabled" = true;',
             []
         );
         $sections = [];
         foreach ($data as $el) {
             array_push($sections,[$el->society=>$el->desc]);
         }

         return (new Response())
             ->setData([
                 'sections' => $sections,
             ])
             ->success();
     }



     public function export(Request $request): \Illuminate\Http\JsonResponse
     {
         $validator = \Validator::make($request->only(self::ARRAY_ATTR), [
             self::ATTR_DATE => 'required|date_format:Y',
             self::ATTR_SECTION => 'required|numeric',
             self::ATTR_FORMAT => 'required|in:' . implode(',', [Export::formatXlsx, Export::formatPdf]),
         ]);
         if ($validator->fails()) {
             return (new Response())
                 ->setPopup('Неверные данные', 'В фильтр переданы неверные данные')
                 ->setData([
                     'errors' => $validator->errors(),
                 ])->fail();
         }
         $formatDocument = $request->post('formatDocument');

         try {
             $result = (new ViolatorsReportExport($request->only(self::ARRAY_ATTR)))
                 ->setTypeResponseBlob()
                 //->setTypeResponseSaveFile()
                 ->build($formatDocument);

         } catch (\Exception $e) {
             return (new Response())->setPopup('Возникла ошибка', $e->getMessage())->fail('', 400);
         }

         return (new Response())->setData([
             'blob' => $result['blob'],
             'name' => $result['name'],
         ])->success();
     }


}
