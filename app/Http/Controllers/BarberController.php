<?php

namespace App\Http\Controllers;

use App\Models\BarberAvailability;
use App\Models\BarberPhotos;
use App\Models\Barbers;
use App\Models\BarberService;
use App\Models\BarberTestimonials;
use App\Models\UserAppointment;
use App\Models\UserFavorite;
use Illuminate\Http\Request;

class BarberController extends Controller
{
    private $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:api');
        $this->loggedUser = auth()->user();
    }

    private function searchGeo($address){
        $key = env('MAPS_KEY', null);

        $address = urlencode($address);

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.$address.'$key='.$key;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($ch);
        curl_close($ch);

        return json_decode($res, true);
    }

    public function list(Request $request){
        $array = ['error'=>''];

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $city = $request->input('city');

        $offset = $request->input('offset');
        if(!$offset){
            $offset = 0;
        }

        if(!empty($city)){
            $res = $this->searchGeo($city);

            if(count($res['results']) > 0){
                $lat = $res['results'][0]['geometry']['location']['lat'];
                $lng = $res['results'][0]['geometry']['location']['lng'];
            }
        }elseif(!empty($lat) && !empty($lng)){
            $res = $this->searchGeo($lat.','.$lng);

            if(count($res['results']) > 0){
                $city = $res['results'][0]['formatted_address'];
            }
        }else {
            $lat = '-23.5630907';
            $lng = '-46.6682795';
            $city = 'São Paulo';
        }

        $barbers = Barbers::select(Barbers::raw('*, SQRT(
            POW(69.1 * (latitude - '.$lat.'), 2) +
            POW(69.1 * ('.$lng.' - longitude) * COS(latitude / 57.3), 2)) AS distance'))
            ->havingRaw('distance < ?', [10])
            ->orderBy('distance', 'ASC')
            ->offset($offset)
            ->limit(5)
            ->get();
        foreach ($barbers as $key => $value):
            $barbers[$key]['avatar'] = url('media/avatars/'. $barbers[$key]['avatar']);
        endforeach;

        $array['data'] = $barbers;
        $array['loc'] = 'São Paulo';

        return $array;
    }

    /*public function one($id){
        $array = ['error'=>''];

        $barber = Barbers::find($id);

        if($barber){
            $barber['avatar'] = url('media/avatars/'. $barber['avatar']);
            $barber['favorited'] = false;
            $barber['photos'] = [];
            $barber['services'] = [];
            $barber['testimonials'] = [];
            $barber['available'] = [];

            //Pegando fotos dos barbeiro
            $barber['photos'] = BarberPhotos::select(['id', 'url'])
                ->where('id_barber', $barber->id)
                ->get();
            foreach ($barber['photos'] as $key => $value):
                $barber['photos'][$key]['url'] = url('media/uploads/'. $barber['photos'][$key]['url']);
            endforeach;

            //Pegando os serviços do Barbeiro
            $barber['services'] = BarberService::select(['id', 'name', 'price'])
                ->where('id_barber', $barber->id)
                ->get();

            //Pegando os depoimentos do Barbeiro
            $barber['testimonials'] = BarberTestimonials::select(['id', 'name', 'rate', 'body'])
                ->where('id_barber', $barber->id)
                ->get();

            //Pegando as disponibilidades do Barbeiro
            $availability = [];

            //Pegando as disponibilidades crua
            $avails = BarberAvailability::where('id_barber', $barber->id)->get();

            $availsWeekDays = [];
            foreach ($avails as $itemA):
                $availsWeekDays[$itemA['weekday']] = explode(',', $itemA['hours']);
            endforeach;

            //Pegar o agendamento dos próximos 20 dias
            $appointment = [];
            $appQuery = UserAppointment::where('id_barber', $barber->id)
                ->whereBetween('ap_datetime', [
                    date('Y-m-d').'00:00:00',
                    date('Y-m-d', strtotime('20 days')).'23:59:59'
                ])
                ->get();

            foreach ($appQuery as $appItem):
                $appointment= $appItem['ap_datetime'];
            endforeach;

            //gerar disponibilidade real
            for($i=0; $i<20; $i++):
                $timeItem = strtotime('+'.$i.' days');
                $weekDay = date('w', $timeItem);

                if(in_array($weekDay, array_keys($availsWeekDays))){
                    $hours = [];

                    $dayItem = date('Y-m-d', $timeItem);

                    foreach ($availsWeekDays[$weekDay] as $hourItem):
                        $dayFormated = $dayItem.''.$hourItem.':00';
                        //$dayFormated = $dayItem.' '.$hourItem.':00';
                        if(!in_array($dayFormated, $appointment)){
                            $hours[] = $hourItem;
                        }
                    endforeach;

                    if(count($hours) > 0){
                        $availability[] = [
                            'date' => $dayItem,
                            'hours' => $hours
                        ];

                    }
                }
            endfor;



            $barber['available'] = $availability;

            $array['data'] = $barber;

        }else{
            $array['error'] = 'Barbeiro não existe!';
            return $array;
        }

        return $array;
    }*/

    public function one($id) {
        $array = ['error' => ''];
        $barber = Barbers::find($id);
        if($barber) {
            $barber['avatar'] = url('media/avatars/'. $barber['avatar']);
            $barber['favorited'] = false;
            $barber['photos'] = [];
            $barber['services'] = [];
            $barber['testimonial'] = [];
            $barber['available'] = [];
            //verificando favorito
            $cFavorite = UserFavorite::where('id_user', $this->loggedUser->id)
                ->where('id_barber', $barber->id)
                ->count();
            if($cFavorite > 0) {
                $barber['favorited'] = true;
            }
            //pegando fotos do barbeiro
            $barber['photos'] = BarberPhotos::select('id','url')
                ->where('id_barber', $barber->id)
                ->get();
            foreach ($barber['photos'] as $bpkey => $bpvalue) {
                $barber['photos'][$bpkey]['url'] = url('media/uploads/'.$barber['photos'][$bpkey]['url']);
            }
            //pegando os serviços do barbeiro
            $barber['services'] = BarberService::select('id', 'name', 'price')
                ->where('id_barber', $barber->id)
                ->get();
            //pegando os depoimentos do barbeiro
            $barber['testimonials'] = BarberTestimonials::select('id', 'name', 'rate', 'body')
                ->where('id_barber', $barber->id)
                ->get();
            //pegando disponibilidade do barbeiro
            $availability = [];
            //pegando a disponibilidade crua
            $avails =  BarberAvailability::where('id_barber', $barber->id)->get();
            $availsWeekDays = [];
            foreach ($avails as $item) {
                $availsWeekDays[$item['weekday']] = explode(',', $item['hours']);
            }
            //pegar os agendamentos dos próx 20 dias
            $appointments = [];
            $appQuery = UserAppointment::where('id_barber', $barber->id)
                ->whereBetween('ap_datetime', [
                    date('Y-m-d').' 00:00:00',
                    date('Y-m-d', strtotime('+20 days')).' 23:59:59'
                ])
                ->get();
            foreach($appQuery as $appItem) {
                $appointments[] = $appItem['ap_datetime'];
            }
            //gerar disponibilidade real
            for($q=0;$q<20;$q++){
                $timeItem = strtotime('+'.$q.' days');
                $weekday = date('w', $timeItem);
                if(in_array($weekday, array_keys($availsWeekDays))) {
                    $hours = [];
                    $dayItem = date('Y-m-d', $timeItem);
                    foreach($availsWeekDays[$weekday] as $hoursItem) {
                        $dayFormated = $dayItem . ' ' .$hoursItem . ':00';
                        if(!in_array($dayFormated, $appointments)) {
                            $hours[] = $hoursItem;
                        }
                    }
                    if(count($hours) > 0) {
                        $availability[] = [
                            'date' => $dayItem,
                            'hours' => $hours
                        ];
                    }
                }
            }
            $barber['available'] = $availability;
            $array['data'] = $barber;
        } else {
            $array['error'] = 'Barbeiro não encontrado';

            return  $array;
        }
        return  $array;
    }

    public function setAppointment($id, Request  $request){
        //service, year, month, day, hour
        $array = ['error'=>''];

        $service = $request->input('service');
        $year = intval($request->input('year'));
        $month = intval($request->input('month'));
        $day = intval($request->input('day'));
        $hour = intval($request->input('hour'));

        $month= ($month < 10) ? '0'.$month : $month;
        $day  = ($day < 10) ? '0'.$day : $day;
        $hour = ($hour < 10) ? '0'.$hour : $hour;

        //1. verificar se o serviço do barbeiro existe
        $barberService = BarberService::select()
            ->where('id', $service)
            ->where('id_barber', $id)
            ->first();

        if($barberService){
            //2. verificar se a data é real
            $apDate = $year.'-'.$month.'-'.$day.' '.$hour.':00:00';
            //$apDate = $year.'-'.$month.'-'.$day.' '.$hour.':00:00';

            if(strtotime($apDate) > 0){
                //3. verificar se o barbeiro já possui agendamento neste dia/hora
                $apps = UserAppointment::select()
                    ->where('id_barber', $id)
                    ->where('ap_datetime', $apDate)
                    ->count();

                if($apps === 0){
                    //4. verificar se o barbeiro atende nesta data/hora
                    $weekDay = date('w', strtotime($apDate));
                    $avail = BarberAvailability::select()
                        ->where('id_barber', $id)
                        ->where('weekDay', $weekDay)
                        ->first();

                    if($avail){
                        //4.2 verificar se o barbeiro atende nesta hora
                        $hours = explode(',', $avail['hours']);
                        if(in_array($hour.':00', $hours)){
                            //5. fazer o agendamento
                            $newApp = new UserAppointment();
                            $newApp->id_user = $this->loggedUser->id;
                            $newApp->id_barber = $id;
                            $newApp->id_service = $service;
                            $newApp->ap_datetime = $apDate;
                            $newApp->save();

                        } else {
                            $array['error'] = 'Barbeiro não atende nesta hora!';
                        }

                    } else {
                        $array['error'] = 'Barbeiro não atende esse dia!';
                    }



                } else {
                    $array['error'] = 'Barbeiro já possui agendamento neste dia/hora!';
                }


            }else{
                $array['error'] = 'Data inválida!';
            }
        } else {
            $array['error'] = 'Serviço inexistente!';
        }

        return  $array;

    }

    public function search(Request $request){
        $array = ['error' => '', 'list'=>[]];

        $q = $request->input('q');

        if($q){

            $barbers = Barbers::select()
                ->where('name', 'LIKE', '%'.$q.' %')
            ->get();

            foreach ($barbers as $key => $barber){
                $barbers[$key]['avatar'] = url('media/avatars/'.$barbers[$key]['avatar']);
            }

            $array['list'][] = $barbers;


        }else {
            $array['error'] = 'Digite algo para buscar!';
        }

        return  $array;
    }

    public function createRandom(){
        $array = ['error'=>''];
        for($q=0; $q<15; $q++) {
            $names = ['Boniek', 'Paulo', 'Pedro', 'Amanda', 'Leticia', 'Gabriel', 'Gabriela', 'Thais', 'Luiz', 'Diogo', 'José', 'Jeremias', 'Francisco', 'Dirce', 'Marcelo' ];
            $lastnames = ['Santos', 'Silva', 'Santos', 'Silva', 'Alvaro', 'Sousa', 'Diniz', 'Josefa', 'Luiz', 'Diogo', 'Limoeiro', 'Santos', 'Limiro', 'Nazare', 'Mimoza' ];
            $servicos = ['Corte', 'Pintura', 'Aparação', 'Unha', 'Progressiva', 'Limpeza de Pele', 'Corte Feminino'];
            $servicos2 = ['Cabelo', 'Unha', 'Pernas', 'Pernas', 'Progressiva', 'Limpeza de Pele', 'Corte Feminino'];
            $depos = [
                'Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptate consequatur tenetur facere voluptatibus iusto accusantium vero sunt, itaque nisi esse ad temporibus a rerum aperiam cum quaerat quae quasi unde.',
                'Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptate consequatur tenetur facere voluptatibus iusto accusantium vero sunt, itaque nisi esse ad temporibus a rerum aperiam cum quaerat quae quasi unde.',
                'Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptate consequatur tenetur facere voluptatibus iusto accusantium vero sunt, itaque nisi esse ad temporibus a rerum aperiam cum quaerat quae quasi unde.',
                'Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptate consequatur tenetur facere voluptatibus iusto accusantium vero sunt, itaque nisi esse ad temporibus a rerum aperiam cum quaerat quae quasi unde.',
                'Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptate consequatur tenetur facere voluptatibus iusto accusantium vero sunt, itaque nisi esse ad temporibus a rerum aperiam cum quaerat quae quasi unde.'
            ];
            $newBarber = new Barbers();
            $newBarber->name = $names[rand(0, count($names)-1)].' '.$lastnames[rand(0, count($lastnames)-1)];
            $newBarber->avatar = rand(1, 4).'.png';
            $newBarber->starts = rand(2, 4).'.'.rand(0, 9);
            $newBarber->latitude = '-23.5'.rand(0, 9).'30907';
            $newBarber->longitude = '-46.6'.rand(0,9).'82759';
            $newBarber->save();
            $ns = rand(3, 6);
            for($w=0;$w<4;$w++) {
                $newBarberPhoto = new BarberPhotos();
                $newBarberPhoto->id_barber = $newBarber->id;
                $newBarberPhoto->url = rand(1, 5).'.png';
                $newBarberPhoto->save();
            }
            for($w=0;$w<$ns;$w++) {
                $newBarberService = new BarberService();
                $newBarberService->id_barber = $newBarber->id;
                $newBarberService->name = $servicos[rand(0, count($servicos)-1)].' de '.$servicos2[rand(0, count($servicos2)-1)];
                $newBarberService->price = rand(1, 99).'.'.rand(0, 100);
                $newBarberService->save();
            }
            for($w=0;$w<3;$w++) {
                $newBarberTestimonial = new BarberTestimonials();
                $newBarberTestimonial->id_barber = $newBarber->id;
                $newBarberTestimonial->name = $names[rand(0, count($names)-1)];
                $newBarberTestimonial->rate = rand(2, 4).'.'.rand(0, 9);
                $newBarberTestimonial->body = $depos[rand(0, count($depos)-1)];
                $newBarberTestimonial->save();
            }
            for($e=0;$e<4;$e++){
                $rAdd = rand(7, 10);
                $hours = [];
                for($r=0;$r<8;$r++) {
                    $time = $r + $rAdd;
                    if($time < 10) {
                        $time = '0'.$time;
                    }
                    $hours[] = $time.':00';
                }
                $newBarberAvail = new BarberAvailability();
                $newBarberAvail->id_barber = $newBarber->id;
                $newBarberAvail->weekday = $e;
                $newBarberAvail->hours = implode(',', $hours);
                $newBarberAvail->save();
            }
        }
        return $array;
    }


}
