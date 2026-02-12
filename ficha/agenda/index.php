<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">

<div class="container mt-4">
    <div id="calendar"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'timeGridWeek',
        locale: 'pt-br',
        selectable: true,
        editable: true,
        height: "auto",

        events: 'api/listar.php',

        select: function(info){
            let titulo = prompt("Descrição da tattoo:");
            if(!titulo) return;

            fetch('api/salvar.php', {
                method: 'POST',
                body: JSON.stringify({
                    inicio: info.startStr,
                    fim: info.endStr,
                    descricao: titulo
                })
            }).then(()=> calendar.refetchEvents());
        },

        eventDrop: atualizar,
        eventResize: atualizar
    });

    function atualizar(info){
        fetch('api/atualizar.php', {
            method: 'POST',
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
