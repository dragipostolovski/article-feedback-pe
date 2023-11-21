const answers1   = document.querySelectorAll('#question__answers button');
const nonce1     = voting_pe.nonce;
const namespace1 = voting_pe.namespace;
const route1     = '/wp-json/'+namespace1+'/';
const postId1    = voting_pe.post_id;
const loader     = document.querySelector('.c-question__loader');

const requestForm_v1 = (url, data = {}, method = 'post' ) => requestFunction_v1({
    url: route1 + url,
    method: method,
    data: { ...data },
    typeError: url
});

const requestFunction_v1 = async ({
    url, method, data, typeError
}) => {
    const response = await fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Cache-Control': 'no-cache',
            'X-WP-Nonce': nonce1,
        },
        body: new URLSearchParams(data),
    });
    
    if (response.ok) {
        return response.json();
    }

    // eslint-disable-next-line no-throw-literal
    throw ({ typeError, requestData: data, response });
};

const votingpGetCookie = ( name ) => {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');

    for( var i = 0; i < ca.length; i++ ) {
        var c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length,c.length);
    }

    return null;
}

if( 1 == votingpGetCookie('voting_done') ) {
    const response = requestForm_v1( 'voting-results', { post_id: postId1, } );

    response.then(function(data) {
        if( !data.response ) {
            loader.innerHTML = "Error happend, we did not get your response.";
            return;
        }
        
        loader.innerHTML = data.message;
    });
}

if( answers1 ) {
    answers1.forEach((answer) => {
        answer.addEventListener('click', () => {

            if( 1 == votingpGetCookie('voting_done') ) {
                const temp = loader.innerHTML;
                loader.innerHTML = "You already voted.";

                setTimeout(() => {
                    loader.innerHTML = temp;
                }, '2000');

                return;
            }

            loader.innerHTML = '';

            const clicked = answer.dataset.answer;
            loader.innerHTML = "Loading...";

            const response = requestForm_v1( 'voting', {
                post_id: postId1,
                answer: clicked, 
            } );

            response.then(function(data) {
                if( !data.response ) {
                    loader.innerHTML = "Error happend, we did not get your response.";
                    return;
                }
                
                loader.innerHTML = data.message;
            });

        });
    });
}