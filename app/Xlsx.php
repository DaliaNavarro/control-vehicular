<?php
declare(strict_types=1);
/** Small OOXML adapter: explicit text cells, numbers, formulas, freeze panes and row styles.
 * Imports values only. Formula cells are rejected; exported data sheets never contain formulas.
 */
final class Xlsx {
 private const NS='http://schemas.openxmlformats.org/spreadsheetml/2006/main';
 private static function xml(string $s): string {return htmlspecialchars($s,ENT_XML1|ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
 private static function column(int $i): string {$s='';for($i++;$i>0;$i=intdiv($i-1,26))$s=chr(65+($i-1)%26).$s;return $s;}
 public static function write(string $path,array $sheets): void {
  $z=new ZipArchive();if($z->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No se pudo crear el Excel.');
  $ct='<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
  $wb='<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="'.self::NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
  $rels='<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
  foreach(array_values($sheets) as $i=>$sheet){
   $n=$i+1;$ct.='<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
   $wb.='<sheet name="'.self::xml($sheet['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
   $rels.='<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
   $count=count($sheet['rows']);$cols=max(array_map('count',$sheet['rows'])?:[1]);
   $xml='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="'.self::NS.'"><dimension ref="A1:'.self::column($cols-1).max(1,$count).'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="22"/><cols>';
   for($c=0;$c<$cols;$c++)$xml.='<col min="'.($c+1).'" max="'.($c+1).'" width="'.($sheet['widths'][$c]??20).'" customWidth="1"/>';
   $xml.='</cols><sheetData>';
   foreach($sheet['rows'] as $ri=>$r){$rn=$ri+1;$style=$sheet['styles'][$ri]??($ri===0?1:0);$xml.='<row r="'.$rn.'">';
    foreach(array_values($r) as $ci=>$v){$ref=self::column($ci).$rn;$st=$style;$formula=null;
     if(is_array($v)){$st=$v['style']??$st;$formula=$v['formula']??null;$v=$v['value']??'';}
     if($formula!==null)$xml.='<c r="'.$ref.'" s="'.$st.'"><f>'.self::xml($formula).'</f><v>'.self::xml((string)$v).'</v></c>';
     elseif(is_int($v)||is_float($v))$xml.='<c r="'.$ref.'" s="'.($st===0?3:$st).'"><v>'.$v.'</v></c>';
     else $xml.='<c r="'.$ref.'" s="'.$st.'" t="inlineStr"><is><t xml:space="preserve">'.self::xml((string)($v??'')).'</t></is></c>';
    }$xml.='</row>';
   }
   $xml.='</sheetData>';if($count>1&&!empty($sheet['filter']))$xml.='<autoFilter ref="A1:'.self::column($cols-1).$count.'"/>';
   $xml.='<pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
   $z->addFromString('xl/worksheets/sheet'.$n.'.xml',$xml);
  }
  $z->addFromString('[Content_Types].xml',$ct.'</Types>');
  $z->addFromString('_rels/.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $z->addFromString('xl/workbook.xml',$wb.'</sheets><calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>');
  $z->addFromString('xl/_rels/workbook.xml.rels',$rels.'<Relationship Id="styles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
  $z->addFromString('xl/styles.xml','<?xml version="1.0"?><styleSheet xmlns="'.self::NS.'"><numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="#,##0.000"/></numFmts><fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><sz val="11"/><color rgb="FFB91C1C"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF123C50"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE4EFF3"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="8"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1"><alignment wrapText="1"/></xf><xf numFmtId="164" fontId="2" fillId="3" borderId="0" xfId="0" applyNumberFormat="1"><alignment wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="164" fontId="3" fillId="4" borderId="0" xfId="0" applyNumberFormat="1"><alignment wrapText="1"/></xf><xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="3" fillId="4" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="2" fillId="3" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
  if(!$z->close())throw new RuntimeException('No se pudo finalizar el Excel.');
 }
 private static function parse(string $s): SimpleXMLElement {
  if(stripos($s,'<!DOCTYPE')!==false||stripos($s,'<!ENTITY')!==false)throw new RuntimeException('XML no permitido.');
  $prev=libxml_use_internal_errors(true);$x=simplexml_load_string($s,SimpleXMLElement::class,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($prev);
  if(!$x)throw new RuntimeException('El archivo contiene XML inválido.');return $x;
 }
 public static function read(string $path,array $allowed=['Bitacoras','Cargas']): array {
  $z=new ZipArchive();if($z->open($path)!==true)throw new RuntimeException('Selecciona un archivo .xlsx válido.');
  try{
   $total=0;for($i=0;$i<$z->numFiles;$i++){$stat=$z->statIndex($i);$total+=$stat['size'];if($stat['size']>16000000||$total>64000000)throw new RuntimeException('El archivo descomprimido excede el límite permitido.');}
   $get=function($name)use($z){$s=$z->getFromName($name);if($s===false)throw new RuntimeException('Falta una parte requerida del XLSX: '.$name);return self::parse($s);};
   $wb=$get('xl/workbook.xml');$rels=$get('xl/_rels/workbook.xml.rels');$map=[];
   foreach($rels->children('http://schemas.openxmlformats.org/package/2006/relationships') as $r){if((string)$r->attributes()['TargetMode']==='External')continue;$target=(string)$r->attributes()['Target'];if(str_contains($target,'..'))throw new RuntimeException('Ruta XLSX no permitida.');$map[(string)$r->attributes()['Id']]=str_starts_with($target,'/')?ltrim($target,'/'):'xl/'.$target;}
   $shared=[];if($z->locateName('xl/sharedStrings.xml')!==false){$ss=$get('xl/sharedStrings.xml');foreach($ss->children(self::NS)->si as $si){$si->registerXPathNamespace('m',self::NS);$shared[]=implode('',array_map('strval',$si->xpath('.//m:t')));}}
   $out=[];$props=$wb->children(self::NS)->workbookPr;$date1904=isset($props[0])&&(string)$props->attributes()['date1904']==='1';
   foreach($wb->children(self::NS)->sheets->sheet as $s){$name=(string)$s->attributes()['name'];if(!in_array($name,$allowed,true))continue;
    $id=(string)$s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];$xml=$get($map[$id]??'');$data=[];
    foreach($xml->children(self::NS)->sheetData->row as $r){$line=[];foreach($r->c as $c){$ref=(string)$c->attributes()['r'];if(!preg_match('/^([A-Z]{1,3})\d+$/D',$ref,$m))throw new RuntimeException('Referencia de celda inválida.');$index=0;foreach(str_split($m[1]) as $ch)$index=$index*26+ord($ch)-64;$index--;if($index>50)throw new RuntimeException('La hoja tiene demasiadas columnas.');
      if(isset($c->f))throw new RuntimeException("$name, $ref: reemplaza las fórmulas por valores antes de importar.");
      $type=(string)$c->attributes()['t'];$v=(string)$c->v;
      if($type==='s')$v=$shared[(int)$v]??'';
      elseif($type==='inlineStr'){$c->registerXPathNamespace('m',self::NS);$v=implode('',array_map('strval',$c->xpath('.//m:t')));}
      elseif($type==='e')throw new RuntimeException("$name, $ref contiene un error de Excel.");
      $line[$index]=$v;
     }
     if(array_filter($line,fn($v)=>trim($v)!=='')){$line['_row']=(int)$r->attributes()['r'];$data[]=$line;}if(count($data)>2001)throw new RuntimeException('Máximo 2000 filas por hoja y archivo.');
    }
    $out[$name]=['data'=>$data,'date1904'=>$date1904];
   }return $out;
  }finally{$z->close();}
 }
 public static function download(array $sheets,string $name): never {
  $path=tempnam(sys_get_temp_dir(),'cv-xlsx-');try{self::write($path,$sheets);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$name.'"');header('Content-Length: '.filesize($path));header('Cache-Control: no-store');readfile($path);}finally{unlink($path);}exit;
 }
}
