<?php
// admin/assets/icons/icons.php

function getIcon(string $name): string
{
    $icons = [
        'link' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 4C6.48 4 2 8.48 2 14C2 19.52 6.48 24 12 24C17.52 24 22 19.52 22 14C22 8.48 17.52 4 12 4ZM12 22C7.58 22 4 18.42 4 14C4 9.58 7.58 6 12 6C16.42 6 20 9.58 20 14C20 18.42 16.42 22 12 22ZM15.28 9.72C14.89 9.33 14.22 9.33 13.83 9.72L11 12.55V7C11 6.45 10.55 6 10 6C9.45 6 9 6.45 9 7V13C9 13.55 9.45 14 10 14H16C16.55 14 17 13.55 17 13C17 12.45 16.55 12 16 12H11.83L15.28 9.72Z"/></svg>',
        'image' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M21 3H3C2.45 3 2 3.45 2 4V20C2 20.55 2.45 21 3 21H21C21.55 21 22 20.55 22 20V4C22 3.45 21.55 3 21 3ZM20 19H4V5H20V19ZM12 7C11.45 7 11 7.45 11 8C11 8.55 11.45 9 12 9C12.55 9 13 8.55 13 8C13 7.45 12.55 7 12 7ZM16 16H8C7.45 16 7 15.55 7 15C7 14.45 7.45 14 8 14H16C16.55 14 17 14.45 17 15C17 15.55 16.55 16 16 16Z"/></svg>',
        'undo' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M19.07 4.93L12 12H7V9H10.59L4.88 3.29L6.29 1.88L12 7.59L17.71 2.88L19.07 4.93ZM18 13V16H6V13H18Z"/></svg>',
        'redo' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M4.93 4.93L12 12H7V9H10.59L4.88 3.29L6.29 1.88L12 7.59L17.71 2.88L19.07 4.93ZM6 13V16H18V13H6Z"/></svg>',
        'folder' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M10 4H2C1.45 4 1 4.45 1 5V19C1 19.55 1.45 20 2 20H22C22.55 20 23 19.55 23 19V5C23 4.45 22.55 4 22 4H12L10 2H2C1.45 2 1 2.45 1 3V5C1 5.55 1.45 6 2 6H10L12 4H10Z"/></svg>',
        'bold' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M14.29 2H6C4.9 2 4 2.9 4 4V20C4 21.1 4.9 22 6 22H14.29C15.39 22 16.29 21.1 16.29 20V4C16.29 2.9 15.39 2 14.29 2ZM6 4H14.29V20H6V4ZM13 12H7V10H13V12ZM13 16H7V14H13V16Z"/></svg>',
        'italic' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M10 4V3H4V5H6V21H4V23H10V22H8V6H10V4Z"/></svg>',
        'underline' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M19 18H5V20H19V18ZM12 2V16H10V2H12Z"/></svg>',
        'h1' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 3V1H5V3H9V21H5V23H9V21H15V23H19V21H15V3H12Z"/></svg>',
        'h2' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M8 3H5V21H8V16H16V21H19V3H16V8H8V3Z"/></svg>',
        'h3' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M8 3H5V21H8V16H16V21H19V3H16V8H8V3Z"/></svg>',
        'h4' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M8 3H5V21H8V16H16V21H19V3H16V8H8V3Z"/></svg>',
        'h5' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M8 3H5V21H8V16H16V21H19V3H16V8H8V3Z"/></svg>',
        'h6' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M8 3H5V21H8V16H16V21H19V3H16V8H8V3Z"/></svg>',
        'ul' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M3 11H21V9H3V11ZM3 7H21V5H3V7ZM3 15H21V13H3V15ZM3 19H21V17H3V19Z"/></svg>',
        'quote' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M17 7V2L13 6L17 10V7ZM13 17V12L9 16L13 20V17Z"/></svg>',
        'code' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M7 5H5V19H7V5ZM11 5H9V19H11V5ZM15 5H13V19H15V5ZM19 5H17V19H19V5Z"/></svg>',
        'div' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M3 4V20H21V4H3ZM5 18V6H19V18H5Z"/></svg>',
        'p' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M19 4V6H5V4H19ZM5 8H19V16H5V8ZM5 18H19V20H5V18Z"/></svg>',
         'edit' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M3,17.25V21h3.75L15.06,12.66l-3.75-3.75L3,17.25zM20.71,7.04l-2.79-2.79c-0.39-0.39-1.02-0.39-1.41,0l-2.5,2.5l3.75,3.75l2.5-2.5C21.1,8.06,21.1,7.43,20.71,7.04z"/></svg>',
        'delete' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M6 19C6 19.55 6.45 20 7 20H17C17.55 20 18 19.55 18 19V7H6V19ZM16 4V5H8V4H10V1H14V4H16Z"/></svg>',
       'arrow-up' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M4 12l1.41 1.41L11 7.83V20h2V7.83l5.59 5.58L20 12l-8-8-8 8z"/></svg>',
    'arrow-down' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M4 12l1.41-1.41L11 16.17V4h2v12.17l5.59-5.58L20 12l-8 8-8-8z"/></svg>',
    'image' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M21 3H3C2.45 3 2 3.45 2 4V20C2 20.55 2.45 21 3 21H21C21.55 21 22 20.55 22 20V4C22 3.45 21.55 3 21 3ZM20 19H4V5H20V19ZM12 7C11.45 7 11 7.45 11 8C11 8.55 11.45 9 12 9C12.55 9 13 8.55 13 8C13 7.45 12.55 7 12 7ZM16 16H8C7.45 16 7 15.55 7 15C7 14.45 7.45 14 8 14H16C16.55 14 17 14.45 17 15C17 15.55 16.55 16 16 16Z"/></svg>',
        'txt'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M6 2H18C19.1 2 20 2.9 20 4V20C20 21.1 19.1 22 18 22H6C4.9 22 4 21.1 4 20V4C4 2.9 4.9 2 6 2ZM8 6H16V8H8V6ZM8 10H16V12H8V10ZM8 14H16V16H8V14Z"/></svg>',
        'pdf'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M6 2H18C19.1 2 20 2.9 20 4V20C20 21.1 19.1 22 18 22H6C4.9 22 4 21.1 4 20V4C4 2.9 4.9 2 6 2ZM8 6H16V8H8V6ZM8 10H16V12H8V10ZM8 14H16V16H8V14Z"/></svg>',
        'zip'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M6 2H18C19.1 2 20 2.9 20 4V20C20 21.1 19.1 22 18 22H6C4.9 22 4 21.1 4 20V4C4 2.9 4.9 2 6 2ZM8 6H16V8H8V6ZM8 10H16V12H8V10ZM8 14H16V16H8V14Z"/></svg>',
        'file'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M6 2H18C19.1 2 20 2.9 20 4V20C20 21.1 19.1 22 18 22H6C4.9 22 4 21.1 4 20V4C4 2.9 4.9 2 6 2ZM6 4V20H18V4H6Z"/></svg>',
  
    ];

    if (!isset($icons[$name])) {
        return '';
    }

    // SVG immer a11y-sicher machen
    return str_replace(
        '<svg',
        '<svg aria-hidden="true" focusable="false" width="24" height="24" xmlns="http://www.w3.org/2000/svg"',
        $icons[$name]
    );
}

?>
