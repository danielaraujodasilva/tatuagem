<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Agenda de Tatuagens</title>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">

<style>
body{ background:#111; color:#fff; }
#calendar{ background:#1c1c1c; padding:15px; border-radius:10px; }
</style>
</head>

<body>
<div class="container">
    <h2>Agenda</h2>
    <div id="calendar"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {

        locale: 'pt-br',
        initialView: 'timeGridWeek',
        timeZone: 'local',
        selectable: true,
        editable: true,
        height: "auto",

        events: 'api/listar.php',

        select: function(info){
            let titulo = prompt("Descrição da tattoo:");
            if(!titulo) return;

            fetch('api/salvar.php',{
                method:'POST',
                body: JSON.stringify({
                    inicio: info.startStr,
                    fim: info.endStr,
                    descricao: titulo
                })
            }).then(()=> calendar.refetchEvents());
        },

        eventDrop: atualizar,
        eventResize: atualizar,

        eventClick: function(info){
            if(confirm("Excluir essa tattoo?")){
                fetch('api/deletar.php',{
                    method:'POST',
                    body: JSON.stringify({id: info.event.id})
                }).then(()=> info.event.remove());
            }
        }

    });

    function atualizar(info){
        fetch('api/atualizar.php',{
            method:'POST',
            body: JSON.stringify({
                id: info.event.id,
                inicio: info.event.startStr,
                fim: info.event.endStr
            })
        });
    }

    calendar.render();
});
</script>
</body>
</html>
